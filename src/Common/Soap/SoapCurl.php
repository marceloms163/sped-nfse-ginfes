<?php

namespace NFePHP\NFSeGinfes\Common\Soap;

/**
 * SoapClient based in cURL class
 *
 * @category  NFePHP
 * @package   NFePHP\NFSeGinfes
 * @copyright NFePHP Copyright (c) 2020
 * @license   http://www.gnu.org/licenses/lgpl.txt LGPLv3+
 * @license   https://opensource.org/licenses/MIT MIT
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @author    Cleiton Perin <cperin20 at gmail dot com>
 * @link      http://github.com/nfephp-org/sped-nfse-ginfes for the canonical source repository
 */

use NFePHP\NFSeGinfes\Common\Soap\SoapBase;
use NFePHP\NFSeGinfes\Common\Soap\SoapInterface;
use NFePHP\Common\Exception\SoapException;
use NFePHP\Common\Certificate;
use Psr\Log\LoggerInterface;

class SoapCurl extends SoapBase implements SoapInterface
{
    /**
     * Hosts com stack TLS legado que exigem fallback via openssl CLI.
     * Hoje SBC/GissOnline é o principal caso conhecido.
     *
     * @var string[]
     */
    private $legacyRenegotiationHosts = [
        'isssbc.com.br'
    ];

    /**
     * Constructor
     * @param Certificate $certificate
     * @param LoggerInterface $logger
     */
    public function __construct(?Certificate $certificate = null, ?LoggerInterface $logger = null)
    {
        parent::__construct($certificate, $logger);
    }

    /**
     * Send soap message to url
     * @param string $operation
     * @param string $url
     * @param string $action
     * @param string $envelope
     * @param array $parameters
     * @return string
     * @throws \NFePHP\Common\Exception\SoapException
     */
    public function send(
        $operation,
        $url,
        $action,
        $envelope,
        $parameters
    ) {
        $response = '';
        $this->requestHead = implode("\n", $parameters);
        $this->requestBody = $envelope;

        try {
            $this->saveTemporarilyKeyFiles();
            $oCurl = curl_init();
            $this->setCurlProxy($oCurl);
            curl_setopt($oCurl, CURLOPT_URL, $url);
            curl_setopt($oCurl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($oCurl, CURLOPT_CONNECTTIMEOUT, $this->soaptimeout);
            curl_setopt($oCurl, CURLOPT_TIMEOUT, $this->soaptimeout + 20);
            curl_setopt($oCurl, CURLOPT_HEADER, 1);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, 0);
            if (!$this->disablesec) {
                curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, 2);
                if (is_file((string) $this->casefaz)) {
                    curl_setopt($oCurl, CURLOPT_CAINFO, $this->casefaz);
                }
            }
            curl_setopt($oCurl, CURLOPT_SSLVERSION, $this->soapprotocol);
            curl_setopt($oCurl, CURLOPT_SSLCERT, $this->tempdir . $this->certfile);
            curl_setopt($oCurl, CURLOPT_SSLKEY, $this->tempdir . $this->prifile);
            if (!empty($this->temppass)) {
                curl_setopt($oCurl, CURLOPT_KEYPASSWD, $this->temppass);
            }
            curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, true);
            if (! empty($envelope)) {
                curl_setopt($oCurl, CURLOPT_POST, true);
                curl_setopt($oCurl, CURLOPT_POSTFIELDS, $envelope);
                curl_setopt($oCurl, CURLOPT_HTTPHEADER, $parameters);
            }
            $response = curl_exec($oCurl);
            $this->soaperror = curl_error($oCurl);
            $soapessor_code = curl_errno($oCurl);
            $ainfo = curl_getinfo($oCurl);
            if (is_array($ainfo)) {
                $this->soapinfo = $ainfo;
            }
            $headsize = curl_getinfo($oCurl, CURLINFO_HEADER_SIZE);
            $httpcode = curl_getinfo($oCurl, CURLINFO_HTTP_CODE);
            if ($this->mustUseLegacyRenegotiationFallback($url, $this->soaperror, $soapessor_code)) {
                curl_close($oCurl);
                return $this->sendWithOpenSslLegacyRenegotiation(
                    $operation,
                    $url,
                    $envelope,
                    $parameters
                );
            }
            curl_close($oCurl);
            $this->responseHead = trim(substr($response, 0, $headsize));
            $this->responseBody = trim(substr($response, $headsize));
            $this->saveDebugFiles(
                $operation,
                $this->requestHead . "\n" . $this->requestBody,
                $this->responseHead . "\n" . $this->responseBody
            );
        } catch (\Exception $e) {
            throw SoapException::unableToLoadCurl($e->getMessage());
        }
        if ($this->soaperror != '') {
            throw SoapException::soapFault(
                $this->soaperror . " [$url]",
                $soapessor_code
            );
        }
        if ($httpcode != 200) {
            throw SoapException::soapFault(
                " [$url] HTTP Error code: $httpcode - "
                    . $this->getFaultString($this->responseBody),
                $httpcode
            );
        }
        return $this->responseBody;
    }

    /**
     * Detecta erro de renegociação TLS insegura, comum em stacks legados.
     *
     * @param string $url
     * @param string $soapError
     * @param int $soapErrorCode
     * @return bool
     */
    private function mustUseLegacyRenegotiationFallback($url, $soapError, $soapErrorCode)
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (empty($host)) {
            return false;
        }
        $legacyHost = false;
        foreach ($this->legacyRenegotiationHosts as $suffix) {
            if ($host === $suffix || substr($host, -strlen('.' . $suffix)) === '.' . $suffix) {
                $legacyHost = true;
                break;
            }
        }
        if (!$legacyHost) {
            return false;
        }
        if ((int)$soapErrorCode !== 35 && stripos((string)$soapError, 'unsafe legacy renegotiation') === false) {
            return false;
        }
        return function_exists('proc_open');
    }

    /**
     * Fallback de transporte usando openssl CLI para endpoints com TLS legado.
     *
     * @param string $operation
     * @param string $url
     * @param string $envelope
     * @param array $parameters
     * @return string
     * @throws SoapException
     */
    private function sendWithOpenSslLegacyRenegotiation(
        $operation,
        $url,
        $envelope,
        array $parameters
    ) {
        $parts = parse_url($url);
        if (empty($parts['host'])) {
            throw SoapException::soapFault("URL inválida para fallback openssl [$url]");
        }
        $host = $parts['host'];
        $port = !empty($parts['port']) ? (int)$parts['port'] : 443;
        $path = !empty($parts['path']) ? $parts['path'] : '/';
        if (!empty($parts['query'])) {
            $path .= '?' . $parts['query'];
        }
        $request = "POST {$path} HTTP/1.1\r\n"
            . "Host: {$host}\r\n"
            . implode("\r\n", $parameters) . "\r\n"
            . "Connection: close\r\n\r\n"
            . $envelope;

        $command = 'openssl s_client'
            . ' -quiet -ign_eof -legacy_renegotiation'
            . ' -connect ' . escapeshellarg($host . ':' . $port)
            . ' -servername ' . escapeshellarg($host)
            . ' -cert ' . escapeshellarg($this->tempdir . $this->certfile)
            . ' -key ' . escapeshellarg($this->tempdir . $this->prifile);
        if (!empty($this->temppass)) {
            $command .= ' -pass ' . escapeshellarg('pass:' . $this->temppass);
        }

        $descriptorspec = [
            0 => ['pipe', 'w'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        $process = proc_open($command, $descriptorspec, $pipes);
        if (!is_resource($process)) {
            throw SoapException::soapFault("Não foi possível iniciar fallback openssl [$url]");
        }
        fwrite($pipes[0], $request);
        fclose($pipes[0]);
        $response = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 && empty($response)) {
            throw SoapException::soapFault(
                trim("Fallback openssl falhou [{$url}] {$stderr}"),
                $exitCode
            );
        }

        $response = ltrim((string)$response);
        $parts = preg_split("/\r\n\r\n|\n\n/", $response, 2);
        $this->responseHead = trim($parts[0] ?? '');
        $this->responseBody = trim($parts[1] ?? '');
        $this->soaperror = '';
        $this->soapinfo = [
            'fallback' => 'openssl-legacy-renegotiation',
            'stderr' => trim($stderr)
        ];
        $this->saveDebugFiles(
            $operation,
            $this->requestHead . "\n" . $this->requestBody,
            $this->responseHead . "\n" . $this->responseBody
        );

        $httpcode = 0;
        if (preg_match('/^HTTP\/\d+(?:\.\d+)?\s+(\d+)/', $this->responseHead, $matches)) {
            $httpcode = (int)$matches[1];
        }
        if ($httpcode !== 200) {
            throw SoapException::soapFault(
                " [$url] HTTP Error code: $httpcode - "
                    . $this->getFaultString($this->responseBody),
                $httpcode
            );
        }
        return $this->responseBody;
    }

    /**
     * Recover WSDL form given URL
     * @param string $url
     * @return string
     */
    public function wsdl($url)
    {
        $response = '';
        $this->saveTemporarilyKeyFiles();
        $url .= '?Wsdl'; //singleWsdl
        $oCurl = curl_init();
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($oCurl, CURLOPT_CONNECTTIMEOUT, $this->soaptimeout);
        curl_setopt($oCurl, CURLOPT_TIMEOUT, $this->soaptimeout + 20);
        curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($oCurl, CURLOPT_SSLVERSION, $this->soapprotocol);
        curl_setopt($oCurl, CURLOPT_SSLCERT, $this->tempdir . $this->certfile);
        curl_setopt($oCurl, CURLOPT_SSLKEY, $this->tempdir . $this->prifile);
        if (!empty($this->temppass)) {
            curl_setopt($oCurl, CURLOPT_KEYPASSWD, $this->temppass);
        }
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($oCurl);
        $soaperror = curl_error($oCurl);
        $ainfo = curl_getinfo($oCurl);
        $headsize = curl_getinfo($oCurl, CURLINFO_HEADER_SIZE);
        $httpcode = curl_getinfo($oCurl, CURLINFO_HTTP_CODE);
        curl_close($oCurl);
        if ($httpcode != 200) {
            return '';
        }
        return $response;
    }

    /**
     * Set proxy into cURL parameters
     * @param resource $oCurl
     */
    private function setCurlProxy(&$oCurl)
    {
        if ($this->proxyIP != '') {
            curl_setopt($oCurl, CURLOPT_HTTPPROXYTUNNEL, 1);
            curl_setopt($oCurl, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
            curl_setopt($oCurl, CURLOPT_PROXY, $this->proxyIP . ':' . $this->proxyPort);
            if ($this->proxyUser != '') {
                curl_setopt($oCurl, CURLOPT_PROXYUSERPWD, $this->proxyUser . ':' . $this->proxyPass);
                curl_setopt($oCurl, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
            }
        }
    }

    /**
     * Extract faultstring form response if exists
     * @param string $body
     * @return string
     */
    private function getFaultString($body)
    {
        if (empty($body)) {
            return '';
        }
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($body);
        $faultstring = '';
        $nodefault = !empty($dom->getElementsByTagName('faultstring')->item(0))
            ? $dom->getElementsByTagName('faultstring')->item(0)
            : '';
        if (!empty($nodefault)) {
            $faultstring = $nodefault->nodeValue;
        }
        return htmlentities($faultstring, ENT_QUOTES, 'UTF-8');
    }
}
