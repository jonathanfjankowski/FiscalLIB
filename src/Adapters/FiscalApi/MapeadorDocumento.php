<?php

declare(strict_types=1);

namespace FiscalLib\Adapters\FiscalApi;

use FiscalLib\Documento\ItemFiscal;
use FiscalLib\Documento\NfeDocumento;
use FiscalLib\Documento\NfseDocumento;
use FiscalLib\Tax\Resultados\NfeTaxResultado;

/**
 * Modelo intermediário → JSON do contrato da FiscalAPI (EmissaoRequest /
 * NfseDpsRequest). Campos monetários/decimais saem como números JSON —
 * o ASP.NET Core do receptor desserializa decimais somente de números.
 * Defaults do contrato aplicados aqui: gtin "SEM GTIN", unidade "UN".
 */
final class MapeadorDocumento
{
    /** Campos que viram número JSON (tudo que começa com base/aliquota/valor/percentual/fcp + quantidadeTributavel). */
    private const PREFIXOS_NUMERICOS = '/^(base|aliquota|valor|percentual|fcp|quantidadeTributavel)/';

    public function paraEmissaoRequest(NfeDocumento $documento): array
    {
        $itens = [];
        foreach ($documento->itens as $item) {
            $itens[] = $this->paraItem($item);
        }

        $request = [
            'ambiente' => $documento->ambiente->value,
            'serie' => $documento->serie,
            'itens' => $itens,
            'totais' => $this->paraTotais($documento),
        ];

        if ($documento->naturezaOperacao !== null) {
            $request['naturezaOperacao'] = $documento->naturezaOperacao;
        }
        $request['finalidade'] = $documento->finalidade->value;
        $request['tipoOperacao'] = $documento->tipoOperacao->value;
        if ($documento->indicadorPresenca !== null) {
            $request['indicadorPresenca'] = $documento->indicadorPresenca->value;
        }
        $request['indicadorConsumidorFinal'] = $documento->indicadorConsumidorFinal->value;

        if ($documento->destinatario !== null) {
            $request['destinatario'] = [
                'cnpjCpf' => $documento->destinatario->cnpjCpf(),
                'nome' => $documento->destinatario->nome,
            ];
            if ($documento->destinatario->inscricaoEstadual !== null) {
                $request['destinatario']['inscricaoEstadual'] = $documento->destinatario->inscricaoEstadual;
            }
            $endereco = self::endereco($documento->destinatario->endereco);
            if ($endereco !== null) {
                $request['destinatario']['endereco'] = $endereco;
            }
        }

        if ($documento->pagamentos !== []) {
            $pagamentos = [];
            foreach ($documento->pagamentos as $pagamento) {
                $pagamentos[] = [
                    'forma' => $pagamento->formaCodigo(),
                    'valor' => self::num($pagamento->valor),
                ];
            }
            $request['pagamento'] = $pagamentos;
        }

        if ($documento->nfesReferenciadas !== []) {
            $referenciadas = [];
            foreach ($documento->nfesReferenciadas as $referenciada) {
                $referenciadas[] = ['chaveAcesso' => $referenciada->chaveAcesso];
            }
            $request['nfesReferenciadas'] = $referenciadas;
        }

        return $request;
    }

    public function paraNfseDps(NfseDocumento $documento): array
    {
        $t = $documento->tributos;

        $request = [
            'ambiente' => $documento->ambiente->value,
            'serie' => $documento->serie,
            'tomador' => self::paraTomador($documento),
            'servico' => [
                'codigoTributarioNacional' => $documento->servico->codigoTributarioNacional,
                'descricaoServico' => $documento->servico->descricaoServico,
            ],
            'valores' => $this->paraValoresNfse($t),
        ];

        if ($documento->dataCompetencia !== null) {
            $request['dataCompetencia'] = $documento->dataCompetencia;
        }
        if ($documento->tipoEmissor !== null) {
            $request['tipoEmissor'] = $documento->tipoEmissor;
        }
        if ($documento->codigoMunicipioEmissor !== null) {
            $request['codigoMunicipioEmissor'] = $documento->codigoMunicipioEmissor;
        }
        if ($documento->servico->codigoNbs !== null) {
            $request['servico']['codigoNbs'] = $documento->servico->codigoNbs;
        }
        if ($documento->servico->codigoTributarioMunicipal !== null) {
            $request['servico']['codigoTributarioMunicipal'] = $documento->servico->codigoTributarioMunicipal;
        }
        if ($documento->servico->codigoMunicipioPrestacao !== null) {
            $request['servico']['codigoMunicipioPrestacao'] = $documento->servico->codigoMunicipioPrestacao;
        }

        if ($documento->ibsCbs !== null) {
            $ibscbs = [
                'finalidade' => $documento->ibsCbs->finalidade,
                'codigoIndicadorOperacao' => $documento->ibsCbs->codigoIndicadorOperacao,
                'gibbsCbs' => [
                    'cst' => $documento->ibsCbs->cstIbsCbs,
                    'cClassTrib' => $documento->ibsCbs->cClassTrib,
                ],
            ];
            if ($documento->ibsCbs->indicadorFinal !== null) {
                $ibscbs['indicadorFinal'] = $documento->ibsCbs->indicadorFinal;
            }
            if ($documento->ibsCbs->indicadorDestinatario !== null) {
                $ibscbs['indicadorDestinatario'] = $documento->ibsCbs->indicadorDestinatario;
            }
            if ($documento->ibsCbs->tipoOperacaoGov !== null) {
                $ibscbs['tipoOperacaoGov'] = $documento->ibsCbs->tipoOperacaoGov;
            }
            if ($documento->ibsCbs->tipoEnteGovernamental !== null) {
                $ibscbs['tipoEnteGovernamental'] = $documento->ibsCbs->tipoEnteGovernamental;
            }
            if ($documento->ibsCbs->codigoCreditoPresumido !== null) {
                $ibscbs['gibbsCbs']['codigoCreditoPresumido'] = $documento->ibsCbs->codigoCreditoPresumido;
            }
            $request['ibscbs'] = $ibscbs;
        }

        if ($documento->informacoesComplementares !== null) {
            $request['informacoesComplementares'] = $documento->informacoesComplementares;
        }

        return $request;
    }

    // ------------------------------------------------------------------ itens

    private function paraItem(ItemFiscal $item): array
    {
        $dados = [
            'codigo' => $item->codigo,
            'descricao' => $item->descricao,
            'quantidade' => self::num(\FiscalLib\Common\Matematica::escalar($item->quantidade, 4)),
            'valorUnitario' => self::num(\FiscalLib\Common\Matematica::escalar($item->valorUnitario, 10)),
            'valorTotal' => self::num($item->valorTotal),
            'gtin' => $item->gtin ?? 'SEM GTIN',
            'unidade' => $item->unidade ?? 'UN',
        ];

        if ($item->ncm !== null) {
            $dados['ncm'] = $item->ncm;
        }
        if ($item->cfop !== null) {
            $dados['cfop'] = $item->cfop;
        }
        if ($item->cest !== null) {
            $dados['cest'] = $item->cest;
        }
        if (bccomp($item->valorDesconto, '0', 2) !== 0) {
            $dados['valorDesconto'] = self::num($item->valorDesconto);
        }

        $impostosV2 = $item->tributos?->paraArray();
        if ($impostosV2 !== null && $impostosV2 !== []) {
            $dados['impostosV2'] = self::numerificar($impostosV2);
        }

        return $dados;
    }

    private function paraTotais(NfeDocumento $documento): array
    {
        $t = $documento->totais;
        $totais = [
            'valorProdutos' => self::num($t->valorProdutos),
            'valorNota' => self::num($t->valorNota),
        ];
        foreach (['valorDesconto', 'valorFrete', 'valorSeguro', 'outrasDespesas', 'valorIbs', 'valorCbs', 'valorIs'] as $campo) {
            $valor = $t->{$campo};
            if ($valor !== null) {
                $totais[$campo] = self::num($valor);
            }
        }

        return $totais;
    }

    private function paraTomador(NfseDocumento $documento): array
    {
        $tomador = ['cnpjCpf' => $documento->tomador->cnpjCpf()];
        if ($documento->tomador->nome !== null) {
            $tomador['nome'] = $documento->tomador->nome;
        }
        if ($documento->tomador->inscricaoMunicipal !== null) {
            $tomador['inscricaoMunicipal'] = $documento->tomador->inscricaoMunicipal;
        }
        if ($documento->tomador->telefone !== null) {
            $tomador['telefone'] = $documento->tomador->telefone;
        }
        if ($documento->tomador->email !== null) {
            $tomador['email'] = $documento->tomador->email;
        }

        $endereco = $documento->tomador->endereco;
        if ($endereco !== null) {
            $tomador['endereco'] = [
                'codigoMunicipioIbge' => $endereco->codigoMunicipioIbge,
                'cep' => $endereco->cep,
                'logradouro' => $endereco->logradouro,
                'numero' => $endereco->numero,
                'bairro' => $endereco->bairro,
            ];
            if ($endereco->complemento !== null) {
                $tomador['endereco']['complemento'] = $endereco->complemento;
            }
        }

        return $tomador;
    }

    private function paraValoresNfse(\FiscalLib\Tax\Resultados\NfseTaxResultado $t): array
    {
        $valores = [
            'valorServicos' => self::num($t->valorServicos),
            'tributacaoIssqn' => $t->tributacaoIssqn,
            'retencaoIssqn' => $t->retencaoIssqn,
        ];

        if (bccomp($t->valorRecebido, '0', 2) !== 0) {
            $valores['valorRecebido'] = self::num($t->valorRecebido);
        }
        if (bccomp($t->descontoIncondicionado, '0', 2) !== 0) {
            $valores['descontoIncondicionado'] = self::num($t->descontoIncondicionado);
        }
        if ($t->aliquotaIssqn !== null) {
            $valores['aliquotaIssqn'] = self::num($t->aliquotaIssqn);
        }

        if ($t->cstPisCofins !== null) {
            $valores['tributacaoFederal'] = self::numerificar([
                'cstPisCofins' => $t->cstPisCofins,
                'baseCalculoPisCofins' => $t->baseCalculoPisCofins,
                'aliquotaPis' => $t->aliquotaPis,
                'valorPis' => $t->valorPis,
                'aliquotaCofins' => $t->aliquotaCofins,
                'valorCofins' => $t->valorCofins,
                'tipoRetencaoPisCofins' => $t->tipoRetencaoPisCofins,
                'valorRetidoCpp' => $t->valorRetidoCpp === '0.00' ? null : $t->valorRetidoCpp,
                'valorRetidoIrrf' => $t->valorRetidoIrrf === '0.00' ? null : $t->valorRetidoIrrf,
                'valorRetidoCsll' => $t->valorRetidoCsll === '0.00' ? null : $t->valorRetidoCsll,
            ]);
        }

        if ($t->totalTributosFederal !== null || $t->totalTributosEstadual !== null || $t->totalTributosMunicipal !== null) {
            $valores['totalTributos'] = self::numerificar([
                'federal' => $t->totalTributosFederal,
                'estadual' => $t->totalTributosEstadual,
                'municipal' => $t->totalTributosMunicipal,
            ]);
        }

        return $valores;
    }

    // ---------------------------------------------------------------- helpers

    /** @param array<string,mixed> $valores @return array<string,mixed> */
    public static function numerificar(array $valores): array
    {
        $saida = [];
        foreach ($valores as $chave => $valor) {
            if ($valor === null) {
                continue;
            }
            if (is_array($valor)) {
                $saida[$chave] = self::numerificar($valor);
            } elseif (is_string($valor) && preg_match(self::PREFIXOS_NUMERICOS, $chave)) {
                $saida[$chave] = self::num($valor);
            } else {
                $saida[$chave] = $valor;
            }
        }

        return $saida;
    }

    /** String decimal → número JSON (int quando integral para alíquotas "18.00" virarem 18). */
    public static function num(string $valor): float|int
    {
        $escala = \FiscalLib\Common\Matematica::escalar($valor, 10);
        if (preg_match('/^-?\d+\.0+$/', $escala)) {
            return (int) $escala;
        }

        return (float) $escala;
    }

    /** @return array<string,string>|null */
    private static function endereco(?\FiscalLib\Documento\Endereco $endereco): ?array
    {
        if ($endereco === null) {
            return null;
        }

        $dados = array_filter([
            'cep' => $endereco->cep,
            'logradouro' => $endereco->logradouro,
            'numero' => $endereco->numero,
            'complemento' => $endereco->complemento,
            'bairro' => $endereco->bairro,
            'codigoMunicipioIbge' => $endereco->codigoMunicipioIbge,
            'uf' => $endereco->uf,
            'nomeMunicipio' => $endereco->nomeMunicipio,
        ], static fn ($v) => $v !== null);

        return $dados === [] ? null : $dados;
    }
}
