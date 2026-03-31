<?php

namespace NFePHP\NFSeGinfes\Common;

/**
 * Class for RPS XML convertion
 *
 * @category  NFePHP
 * @package   NFePHP\NFSeBetha
 * @copyright NFePHP Copyright (c) 2020
 * @license   http://www.gnu.org/licenses/lgpl.txt LGPLv3+
 * @license   https://opensource.org/licenses/MIT MIT
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @author    Cleiton Perin <cperin20 at gmail dot com>
 * @link      http://github.com/nfephp-org/sped-nfse-betha for the canonical source repository
 */

use DOMNode;
use NFePHP\Common\DOMImproved as Dom;
use stdClass;

class Factory
{
    const LAYOUT_LEGACY = 'legacy';
    const LAYOUT_IBSCBS = 'ibscbs';

    /**
     * @var stdClass
     */
    protected $std;

    /**
     * @var Dom
     */
    protected $dom;

    /**
     * @var DOMNode
     */
    protected $rps;

    /**
     * @var \stdClass
     */
    protected $config;
    protected $layout = self::LAYOUT_LEGACY;

    /**
     * Constructor
     * @param stdClass $std
     */
    public function __construct(stdClass $std)
    {
        $this->std = $std;

        $this->dom = new Dom('1.0', 'UTF-8');
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput = false;
        $this->rps = $this->dom->createElement('tipos:Rps');
    }

    /**
     * Add config
     * @param \stdClass $config
     */
    public function addConfig($config)
    {
        $this->config = $config;
    }

    /**
     * Add render options
     * @param array $options
     */
    public function setOptions(array $options = [])
    {
        if (!empty($options['layout'])) {
            $this->layout = strtolower((string)$options['layout']);
        }
    }

    /**
     * Builder, converts sdtClass Rps in XML Rps
     * NOTE: without Prestador Tag
     * @return string RPS in XML string format
     */
    public function render()
    {
        $infRps = $this->dom->createElement('tipos:InfRps');
        $this->addIdentificacao($infRps);

        $this->dom->addChild(
            $infRps,
            "tipos:DataEmissao",
            $this->std->dataemissao,
            true
        );
        $this->dom->addChild(
            $infRps,
            "tipos:NaturezaOperacao",
            $this->std->naturezaoperacao,
            true
        );
        $this->dom->addChild(
            $infRps,
            "tipos:RegimeEspecialTributacao",
            $this->std->regimeespecialtributacao,
            false
        );
        $this->dom->addChild(
            $infRps,
            "tipos:OptanteSimplesNacional",
            $this->std->optantesimplesnacional,
            true
        );
        $this->dom->addChild(
            $infRps,
            "tipos:IncentivadorCultural",
            $this->std->incentivadorcultural,
            true
        );
        $this->dom->addChild(
            $infRps,
            "tipos:Status",
            $this->std->status,
            true
        );

        $this->addRpsSubstituido($infRps);
        $this->addServico($infRps);
        $this->addPrestador($infRps);
        $this->addTomador($infRps);
        $this->addIntermediario($infRps);
        $this->addOrgaoGerador($infRps);
        $this->addConstrucao($infRps);

        $this->rps->appendChild($infRps);
        $this->dom->appendChild($this->rps);
        return str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $this->dom->saveXML());
    }

    /**
     * Includes Identificacao TAG in parent NODE
     * @param DOMNode $parent
     */
    protected function addIdentificacao(&$parent)
    {
        if (empty($this->std->identificacaorps)) {
            return;
        }
        $id = $this->std->identificacaorps;
        $node = $this->dom->createElement('tipos:IdentificacaoRps');
        $this->dom->addChild(
            $node,
            "tipos:Numero",
            $id->numero,
            true
        );
        $this->dom->addChild(
            $node,
            "tipos:Serie",
            $id->serie,
            true
        );
        $this->dom->addChild(
            $node,
            "tipos:Tipo",
            $id->tipo,
            true
        );
        $parent->appendChild($node);
    }

    /**
     * Includes RpsSubstituido TAG in parent NODE
     * @param DOMNode $parent
     */
    protected function addRpsSubstituido(&$parent)
    {
        if (empty($this->std->rpssubstituido)) {
            return;
        }
        $id = $this->std->rpssubstituido;
        $node = $this->dom->createElement('tipos:RpsSubstituido');
        $this->dom->addChild(
            $node,
            "tipos:Numero",
            $id->numero,
            true
        );
        $this->dom->addChild(
            $node,
            "tipos:Serie",
            $id->serie,
            true
        );
        $this->dom->addChild(
            $node,
            "tipos:Tipo",
            $id->tipo,
            true
        );
        $parent->appendChild($node);
    }

    /**
     * Includes prestador
     * @param DOMNode $parent
     * @return void
     */
    protected function addPrestador(&$parent)
    {
        if (!isset($this->config)) {
            return;
        }
        $node = $this->dom->createElement('tipos:Prestador');
        $this->dom->addChild(
            $node,
            "tipos:Cnpj",
            !empty($this->config->cnpj) ? $this->config->cnpj : null,
            false
        );
        $this->dom->addChild(
            $node,
            "tipos:InscricaoMunicipal",
            $this->config->im,
            true
        );
        $parent->appendChild($node);
    }

    /**
     * Includes Servico TAG in parent NODE
     * @param DOMNode $parent
     */
    protected function addServico(&$parent)
    {
        $serv = $this->std->servico;
        $val = $this->std->servico->valores;
        $node = $this->dom->createElement('tipos:Servico');
        $valnode = $this->dom->createElement('tipos:Valores');
        $this->dom->addChild(
            $valnode,
            "tipos:ValorServicos",
            number_format($val->valorservicos, 2, '.', ''),
            true
        );
        $this->dom->addChild(
            $valnode,
            "tipos:ValorDeducoes",
            isset($val->valordeducoes) ? number_format($val->valordeducoes, 2, '.', '') : null,
            false
        );
        $this->dom->addChild(
            $valnode,
            "tipos:ValorPis",
            isset($val->valorpis) ? number_format($val->valorpis, 2, '.', '') : null,
            false
        );
        $this->dom->addChild(
            $valnode,
            "tipos:ValorCofins",
            isset($val->valorcofins) ? number_format($val->valorcofins, 2, '.', '') : null,
            false
        );
        $this->dom->addChild(
            $valnode,
            "tipos:ValorInss",
            isset($val->valorinss) ? number_format($val->valorinss, 2, '.', '') : null,
            false
        );
        $this->dom->addChild(
            $valnode,
            "tipos:ValorIr",
            isset($val->valorir) ? number_format($val->valorir, 2, '.', '') : null,
            false
        );
        $this->dom->addChild(
            $valnode,
            "tipos:ValorCsll",
            isset($val->valorcsll) ? number_format($val->valorcsll, 2, '.', '') : null,
            false
        );
        $this->dom->addChild(
            $valnode,
            "tipos:IssRetido",
            isset($val->issretido) ? $val->issretido : null,
            false
        );
        $this->dom->addChild(
            $valnode,
            "tipos:ValorIss",
            isset($val->valoriss) ? number_format($val->valoriss, 2, '.', '') : null,
            false
        );
        $this->dom->addChild(
            $valnode,
            "tipos:ValorIssRetido",
            isset($val->valorissretido) ? $val->valorissretido : null,
            false
        );
        $this->dom->addChild(
            $valnode,
            "tipos:OutrasRetencoes",
            isset($val->outrasretencoes) ? number_format($val->outrasretencoes, 2, '.', '') : null,
            false
        );
        $this->dom->addChild(
            $valnode,
            "tipos:BaseCalculo",
            isset($val->basecalculo)
                ? number_format($val->basecalculo, 2, '.', '')
                : null,
            false
        );
        $this->dom->addChild(
            $valnode,
            "tipos:Aliquota",
            isset($val->aliquota) ? $val->aliquota : null,
            false
        );
        $this->dom->addChild(
            $valnode,
            "tipos:ValorLiquidoNfse",
            isset($val->valorliquidonfse) ? number_format($val->valorliquidonfse, 2, '.', '') : null,
            true
        );
        $this->dom->addChild(
            $valnode,
            "tipos:DescontoIncondicionado",
            isset($val->descontoincondicionado) ? number_format($val->descontoincondicionado, 2, '.', '') : null,
            false
        );
        $this->dom->addChild(
            $valnode,
            "tipos:DescontoCondicionado",
            isset($val->descontocondicionado) ? number_format($val->descontocondicionado, 2, '.', '') : null,
            false
        );
        $this->addIbsCbs($valnode, $serv, $val);
        $node->appendChild($valnode);
        $this->dom->addChild(
            $node,
            "tipos:ItemListaServico",
            $serv->itemlistaservico,
            true
        );
        $this->dom->addChild(
            $node,
            "tipos:CodigoCnae",
            isset($serv->codigocnae) ? $serv->codigocnae : null,
            false
        );
        $this->dom->addChild(
            $node,
            "tipos:CodigoTributacaoMunicipio",
            isset($serv->codigotributacaomunicipio) ? $serv->codigotributacaomunicipio : null,
            false
        );
        $this->dom->addChild(
            $node,
            "tipos:Discriminacao",
            $serv->discriminacao,
            true
        );
        $this->dom->addChild(
            $node,
            "tipos:CodigoMunicipio",
            $serv->codigomunicipio,
            true
        );
        $this->dom->addChild(
            $node,
            "tipos:CodigoNbs",
            isset($serv->codigonbs) ? (string)$serv->codigonbs : '0',
            true
        );
        $parent->appendChild($node);
    }

    /**
     * Includes IBSCBS TAG in Servico node for layouts that support it
     * @param DOMNode $parent
     * @param stdClass $serv
     * @param stdClass $val
     * @return void
     */
    protected function addIbsCbs(&$parent, $serv, $val)
    {
        if ($this->layout !== self::LAYOUT_IBSCBS || empty($serv->ibscbs)) {
            return;
        }
        $ibscbs = $serv->ibscbs;

        $trib = $this->dom->createElement('tipos:trib');
        if (!empty($val->tribfed) && !empty($val->tribfed->piscofins)) {
            $tribFed = $this->dom->createElement('tipos:tribFed');
            $piscofins = $this->dom->createElement('tipos:piscofins');
            $pisCofinsData = $val->tribfed->piscofins;
            $this->dom->addChild(
                $piscofins,
                "tipos:CST",
                str_pad($pisCofinsData->cst, 2, '0', STR_PAD_LEFT),
                true
            );
            $this->dom->addChild(
                $piscofins,
                "tipos:vBCPisCofins",
                isset($pisCofinsData->vbcpiscofins)
                    ? number_format($pisCofinsData->vbcpiscofins, 2, '.', '')
                    : null,
                false
            );
            $this->dom->addChild(
                $piscofins,
                "tipos:pAliqPis",
                isset($pisCofinsData->paliqpis)
                    ? number_format($pisCofinsData->paliqpis, 2, '.', '')
                    : null,
                false
            );
            $this->dom->addChild(
                $piscofins,
                "tipos:pAliqCofins",
                isset($pisCofinsData->paliqcofins)
                    ? number_format($pisCofinsData->paliqcofins, 2, '.', '')
                    : null,
                false
            );
            $this->dom->addChild(
                $piscofins,
                "tipos:vPis",
                isset($pisCofinsData->vpis)
                    ? number_format($pisCofinsData->vpis, 2, '.', '')
                    : null,
                false
            );
            $this->dom->addChild(
                $piscofins,
                "tipos:vCofins",
                isset($pisCofinsData->vcofins)
                    ? number_format($pisCofinsData->vcofins, 2, '.', '')
                    : null,
                false
            );
            $this->dom->addChild(
                $piscofins,
                "tipos:tpRetPisCofins",
                isset($pisCofinsData->tpretpiscofins) ? $pisCofinsData->tpretpiscofins : null,
                false
            );
            $tribFed->appendChild($piscofins);
            $trib->appendChild($tribFed);
        }
        $totTrib = $this->dom->createElement('tipos:totTrib');
        $pTotTrib = $this->dom->createElement('tipos:pTotTrib');
        $this->dom->addChild(
            $pTotTrib,
            "tipos:pTotTribFed",
            isset($ibscbs->ptottribfed)
                ? number_format((float) $ibscbs->ptottribfed, 2, '.', '')
                : '0.00',
            true
        );
        $this->dom->addChild(
            $pTotTrib,
            "tipos:pTotTribEst",
            isset($ibscbs->ptottribest)
                ? number_format((float) $ibscbs->ptottribest, 2, '.', '')
                : '0.00',
            true
        );
        $this->dom->addChild(
            $pTotTrib,
            "tipos:pTotTribMun",
            isset($ibscbs->ptottribmun)
                ? number_format((float) $ibscbs->ptottribmun, 2, '.', '')
                : '0.00',
            true
        );
        $totTrib->appendChild($pTotTrib);
        $trib->appendChild($totTrib);
        $parent->appendChild($trib);

        $node = $this->dom->createElement('tipos:IBSCBS');
        $this->dom->addChild(
            $node,
            "tipos:finNFSe",
            isset($ibscbs->finnfse) ? $ibscbs->finnfse : '0',
            true
        );
        $this->dom->addChild(
            $node,
            "tipos:indFinal",
            isset($ibscbs->indfinal) ? $ibscbs->indfinal : '0',
            true
        );
        $this->dom->addChild(
            $node,
            "tipos:cIndOp",
            isset($ibscbs->cindop) ? str_pad($ibscbs->cindop, 6, '0', STR_PAD_LEFT) : '100101',
            true
        );
        $this->dom->addChild(
            $node,
            "tipos:indDest",
            isset($ibscbs->inddest) ? $ibscbs->inddest : '0',
            true
        );

        $valores = $this->dom->createElement('tipos:valores');
        $tribIbsCbs = $this->dom->createElement('tipos:trib');
        $gIBSCBS = $this->dom->createElement('tipos:gIBSCBS');
        $this->dom->addChild(
            $gIBSCBS,
            "tipos:CST",
            isset($ibscbs->cst) ? str_pad($ibscbs->cst, 3, '0', STR_PAD_LEFT) : '000',
            true
        );
        $this->dom->addChild(
            $gIBSCBS,
            "tipos:cClassTrib",
            isset($ibscbs->cclasstrib) ? str_pad($ibscbs->cclasstrib, 6, '0', STR_PAD_LEFT) : '000000',
            true
        );
        $tribIbsCbs->appendChild($gIBSCBS);
        $valores->appendChild($tribIbsCbs);
        $this->dom->addChild(
            $valores,
            "tipos:cLocalidadeIncid",
            isset($ibscbs->clocalidadeincid) ? $ibscbs->clocalidadeincid : $serv->codigomunicipio,
            true
        );
        $this->dom->addChild(
            $valores,
            "tipos:pRedutor",
            isset($ibscbs->predutor)
                ? number_format((float) $ibscbs->predutor, 2, '.', '')
                : '0.00',
            true
        );
        $node->appendChild($valores);
        $parent->appendChild($node);
    }

    /**
     * Includes Tomador TAG in parent NODE
     * @param DOMNode $parent
     */
    protected function addTomador(&$parent)
    {
        $node = $this->dom->createElement('tipos:Tomador');
        if (!isset($this->std->tomador)) {
            return $parent->appendChild($node);
        }
        $tom = $this->std->tomador;
        $ide = $this->dom->createElement('tipos:IdentificacaoTomador');
        $cpfcnpj = $this->dom->createElement('tipos:CpfCnpj');
        if (isset($tom->cnpj)) {
            $this->dom->addChild(
                $cpfcnpj,
                "tipos:Cnpj",
                $tom->cnpj,
                true
            );
        } else {
            $this->dom->addChild(
                $cpfcnpj,
                "tipos:Cpf",
                $tom->cpf,
                true
            );
        }
        $ide->appendChild($cpfcnpj);
        $this->dom->addChild(
            $ide,
            "tipos:InscricaoMunicipal",
            isset($tom->inscricaomunicipal) ? $tom->inscricaomunicipal : null,
            false
        );
        $node->appendChild($ide);
        $this->dom->addChild(
            $node,
            "tipos:RazaoSocial",
            $tom->razaosocial,
            true
        );
        if (!empty($this->std->tomador->endereco)) {
            $end = $this->std->tomador->endereco;
            $endereco = $this->dom->createElement('tipos:Endereco');
            $this->dom->addChild(
                $endereco,
                "tipos:Endereco",
                $end->endereco,
                true
            );
            $this->dom->addChild(
                $endereco,
                "tipos:Numero",
                $end->numero,
                true
            );
            $this->dom->addChild(
                $endereco,
                "tipos:Complemento",
                isset($end->complemento) ? $end->complemento : null,
                false
            );
            $this->dom->addChild(
                $endereco,
                "tipos:Bairro",
                $end->bairro,
                true
            );
            $this->dom->addChild(
                $endereco,
                "tipos:CodigoMunicipio",
                $end->codigomunicipio,
                true
            );
            $this->dom->addChild(
                $endereco,
                "tipos:Uf",
                $end->uf,
                true
            );
            $this->dom->addChild(
                $endereco,
                "tipos:Cep",
                $end->cep,
                true
            );
            $node->appendChild($endereco);
        }
        if (!empty($tom->telefone) || !empty($tom->email)) {
            $contato = $this->dom->createElement('tipos:Contato');
            $this->dom->addChild(
                $contato,
                "tipos:Telefone",
                isset($tom->telefone) ? $tom->telefone : null,
                false
            );
            $this->dom->addChild(
                $contato,
                "tipos:Email",
                isset($tom->email) ? $tom->email : null,
                false
            );
            $node->appendChild($contato);
        }
        $parent->appendChild($node);
    }

    /**
     * Includes Intermediario TAG in parent NODE
     * @param DOMNode $parent
     */
    protected function addIntermediario(&$parent)
    {
        if (!isset($this->std->intermediarioservico)) {
            return;
        }
        $int = $this->std->intermediarioservico;
        $node = $this->dom->createElement('tipos:Intermediario');
        $ide = $this->dom->createElement('tipos:IdentificacaoIntermediario');

        $this->dom->addChild(
            $ide,
            "tipos:RazaoSocial",
            $int->razaosocial,
            true
        );
        $cpfcnpj = $this->dom->createElement('tipos:CpfCnpj');
        if (isset($int->cnpj)) {
            $this->dom->addChild(
                $cpfcnpj,
                "tipos:Cnpj",
                $int->cnpj,
                true
            );
        } else {
            $this->dom->addChild(
                $cpfcnpj,
                "tipos:Cpf",
                $int->cpf,
                true
            );
        }
        $ide->appendChild($cpfcnpj);
        $this->dom->addChild(
            $ide,
            "tipos:InscricaoMunicipal",
            $int->inscricaomunicipal,
            false
        );
        $node->appendChild($ide);
        $parent->appendChild($node);
    }

    /**
     * Includes Construcao TAG in parent NODE
     * @param DOMNode $parent
     */
    protected function addConstrucao(&$parent)
    {
        if (!isset($this->std->construcaocivil)) {
            return;
        }
        $obra = $this->std->construcaocivil;
        $node = $this->dom->createElement('tipos:ConstrucaoCivil');
        $this->dom->addChild(
            $node,
            "tipos:CodigoObra",
            isset($obra->codigoobra) ? $obra->codigoobra : null,
            true
        );
        $this->dom->addChild(
            $node,
            "tipos:Art",
            $obra->art,
            true
        );
        $parent->appendChild($node);
    }

    /**
     * Includes OrgaoGerador TAG in parent NODE
     * @param DOMNode $parent
     */
    protected function addOrgaoGerador(&$parent)
    {
        if (!isset($this->std->orgaogerador)) {
            return;
        }
        $orgao = $this->std->orgaogerador;
        $node = $this->dom->createElement('tipos:OrgaoGerador');
        $this->dom->addChild(
            $node,
            "tipos:CodigoMunicipio",
            $orgao->codigomunicipio,
            true
        );
        $this->dom->addChild(
            $node,
            "tipos:Uf",
            $orgao->uf,
            true
        );
        $parent->appendChild($node);
    }
}
