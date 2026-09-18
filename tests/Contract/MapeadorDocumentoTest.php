<?php

declare(strict_types=1);

namespace FiscalLib\Tests\Contract;

use FiscalLib\Adapters\FiscalApi\MapeadorDocumento;
use FiscalLib\Common\Enums\Ambiente;
use FiscalLib\Common\Enums\FormaPagamento;
use FiscalLib\Common\Enums\UF;
use FiscalLib\Common\ValueObjects\Cnpj;
use FiscalLib\Documento\Destinatario;
use FiscalLib\Documento\Endereco;
use FiscalLib\Documento\IbsCbsDps;
use FiscalLib\Documento\ItemFiscal;
use FiscalLib\Documento\NfeDocumento;
use FiscalLib\Documento\NfseDocumento;
use FiscalLib\Documento\ServicoFiscal;
use FiscalLib\Documento\Tomador;
use FiscalLib\Nfe\NfeBuilder;
use FiscalLib\Nfse\NfseBuilder;
use FiscalLib\Tax\Contextos\NfseTaxContext;
use FiscalLib\Tax\Resultados\ImpostoTrioResultado;
use FiscalLib\Tax\Resultados\IcmsResultado;
use FiscalLib\Tax\Resultados\IcmsStResultado;
use FiscalLib\Tax\Resultados\NfeTaxResultado;
use PHPUnit\Framework\TestCase;

/**
 * Contrato HTTP: o JSON produzido deve casar campo a campo com o que a
 * FiscalAPI valida (EmissaoRequest / NfseDpsRequest, camelCase, números JSON).
 */
final class MapeadorDocumentoTest extends TestCase
{
    private MapeadorDocumento $mapeador;

    protected function setUp(): void
    {
        $this->mapeador = new MapeadorDocumento();
    }

    public function testGoldenJsonNfeCst10ComStEIpi(): void
    {
        $tributos = new NfeTaxResultado(
            icms: new IcmsResultado(
                origem: 0, cst: '10', modBc: '3',
                baseCalculo: '100.00', aliquota: '18.0000', valor: '18.00',
                st: new IcmsStResultado(
                    modBcSt: '4', baseCalculoSt: '130.00', aliquotaSt: '18.0000', valorSt: '23.40',
                ),
            ),
            ipi: new ImpostoTrioResultado(cst: '50', cEnq: '999', baseCalculo: '100.00', aliquota: '10.0000', valor: '10.00'),
            pis: new ImpostoTrioResultado(cst: '01', baseCalculo: '100.00', aliquota: '1.6500', valor: '1.65'),
            cofins: new ImpostoTrioResultado(cst: '01', baseCalculo: '100.00', aliquota: '7.6000', valor: '7.60'),
        );

        $documento = NfeBuilder::nfe()
            ->ambiente(Ambiente::Homologacao)
            ->serie(1)
            ->naturezaOperacao('Venda de mercadoria')
            ->destinatario(new Destinatario(
                Cnpj::criar('11444777000161'),
                'Cliente Teste Ltda',
                endereco: new Endereco(cep: '01001000', logradouro: 'Praça da Sé', numero: '1', bairro: 'Sé', codigoMunicipioIbge: '3550308', uf: UF::SP),
            ))
            ->addItem(new ItemFiscal(
                codigo: 'SKU1',
                descricao: 'Produto de teste',
                quantidade: '2.0000',
                valorUnitario: '50.00',
                valorTotal: '100.00',
                tributos: $tributos,
                ncm: '12345678',
                cfop: '5102',
            ))
            ->pagamento(FormaPagamento::Dinheiro, 100)
            ->frete(10)
            ->build();

        $json = json_encode($this->mapeador->paraEmissaoRequest($documento), JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION);
        $payload = json_decode((string) $json, true);

        $esperado = [
            'ambiente' => 'homologacao',
            'serie' => 1,
            'itens' => [[
                'codigo' => 'SKU1',
                'descricao' => 'Produto de teste',
                'quantidade' => 2,
                'valorUnitario' => 50,
                'valorTotal' => 100,
                'gtin' => 'SEM GTIN',
                'unidade' => 'UN',
                'ncm' => '12345678',
                'cfop' => '5102',
                'impostosV2' => [
                    'icms' => [
                        'origem' => 0,
                        'cst' => '10',
                        'modBc' => '3',
                        'baseCalculo' => 100,
                        'aliquota' => 18,
                        'valor' => 18,
                        'st' => [
                            'modBcSt' => '4',
                            'baseCalculoSt' => 130,
                            'aliquotaSt' => 18,
                            'valorSt' => 23.4,
                        ],
                    ],
                    'ipi' => ['cst' => '50', 'baseCalculo' => 100, 'aliquota' => 10, 'valor' => 10, 'cEnq' => '999'],
                    'pis' => ['cst' => '01', 'baseCalculo' => 100, 'aliquota' => 1.65, 'valor' => 1.65],
                    'cofins' => ['cst' => '01', 'baseCalculo' => 100, 'aliquota' => 7.6, 'valor' => 7.6],
                ],
            ]],
            'totais' => [
                'valorProdutos' => 100,
                'valorNota' => 143.4, // 100 + frete 10 + ST 23.40 + IPI 10 (fórmula v2)
                'valorFrete' => 10,
            ],
            'naturezaOperacao' => 'Venda de mercadoria',
            'finalidade' => 'normal',
            'tipoOperacao' => 'saida',
            'indicadorConsumidorFinal' => 'sim',
            'destinatario' => [
                'cnpjCpf' => '11444777000161',
                'nome' => 'Cliente Teste Ltda',
                'endereco' => [
                    'cep' => '01001000',
                    'logradouro' => 'Praça da Sé',
                    'numero' => '1',
                    'bairro' => 'Sé',
                    'codigoMunicipioIbge' => '3550308',
                    'uf' => 'SP',
                ],
            ],
            'pagamento' => [['forma' => '01', 'valor' => 100]],
        ];

        self::assertSame($esperado, $payload);
        self::assertStringNotContainsString('"valorTotal": "100"', (string) $json, 'decimais devem ser números JSON, não strings');
    }

    public function testGoldenJsonNfseDps(): void
    {
        $documento = NfseBuilder::make()
            ->ambiente(Ambiente::Homologacao)
            ->serie(1)
            ->competencia('2026-09-05')
            ->tomador(new Tomador(
                Cnpj::criar('11444777000161'),
                'Cliente Serviço Ltda',
                endereco: new Endereco(cep: '01001000', logradouro: 'Praça da Sé', numero: '1', bairro: 'Sé', codigoMunicipioIbge: '3550308'),
            ))
            ->servico(new ServicoFiscal('010701', 'Desenvolvimento de software', codigoNbs: '112011000'))
            ->tributos((new \FiscalLib\Tax\TaxEngine())->calcularNfse(
                NfseTaxContext::make()
                    ->servico(1000)
                    ->iss(5, tributacao: 1, retencao: 2)
                    ->pisCofins('01', 0.65, 3.0)
            ))
            ->ibsCbs(new IbsCbsDps('000001', '101', '000001'))
            ->build();

        $payload = $this->mapeador->paraNfseDps($documento);

        $esperado = [
            'ambiente' => 'homologacao',
            'serie' => 1,
            'tomador' => [
                'cnpjCpf' => '11444777000161',
                'nome' => 'Cliente Serviço Ltda',
                'endereco' => [
                    'codigoMunicipioIbge' => '3550308',
                    'cep' => '01001000',
                    'logradouro' => 'Praça da Sé',
                    'numero' => '1',
                    'bairro' => 'Sé',
                ],
            ],
            'servico' => [
                'codigoTributarioNacional' => '010701',
                'descricaoServico' => 'Desenvolvimento de software',
                'codigoNbs' => '112011000',
            ],
            'valores' => [
                'valorServicos' => 1000,
                'tributacaoIssqn' => 1,
                'retencaoIssqn' => 2,
                'aliquotaIssqn' => 5,
                'tributacaoFederal' => [
                    'cstPisCofins' => '01',
                    'baseCalculoPisCofins' => 1000,
                    'aliquotaPis' => 0.65,
                    'valorPis' => 6.5,
                    'aliquotaCofins' => 3,
                    'valorCofins' => 30,
                ],
            ],
            'dataCompetencia' => '2026-09-05',
            'ibscbs' => [
                'finalidade' => 0,
                'codigoIndicadorOperacao' => '000001',
                'gibbsCbs' => ['cst' => '101', 'cClassTrib' => '000001'],
                'indicadorFinal' => 1,
                'indicadorDestinatario' => 0,
            ],
        ];

        self::assertSame($esperado, $payload);
    }
}
