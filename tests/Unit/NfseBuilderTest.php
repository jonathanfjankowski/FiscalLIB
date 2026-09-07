<?php

declare(strict_types=1);

namespace FiscalLib\Tests\Unit;

use FiscalLib\Common\Enums\Ambiente;
use FiscalLib\Common\ValueObjects\Cnpj;
use FiscalLib\Documento\Endereco;
use FiscalLib\Documento\IbsCbsDps;
use FiscalLib\Documento\ServicoFiscal;
use FiscalLib\Documento\Tomador;
use FiscalLib\Exceptions\ValidationException;
use FiscalLib\Nfse\NfseBuilder;
use FiscalLib\Tax\Contextos\NfseTaxContext;
use FiscalLib\Tax\TaxEngine;
use PHPUnit\Framework\TestCase;

final class NfseBuilderTest extends TestCase
{
    private function tomador(): Tomador
    {
        return new Tomador(
            Cnpj::criar('11444777000161'),
            'Cliente Serviço Ltda',
            endereco: new Endereco(
                cep: '01001000',
                logradouro: 'Praça da Sé',
                numero: '1',
                bairro: 'Sé',
                codigoMunicipioIbge: '3550308',
            ),
        );
    }

    private function tributos(): \FiscalLib\Tax\Resultados\NfseTaxResultado
    {
        return (new TaxEngine())->calcularNfse(
            NfseTaxContext::make()->servico(1000)->iss(5)->pisCofins('01', 0.65, 3.0)
        );
    }

    private function ibsCbs(): IbsCbsDps
    {
        return new IbsCbsDps(
            codigoIndicadorOperacao: '000001',
            cstIbsCbs: '101',
            cClassTrib: '000001',
        );
    }

    public function testBuildCompleto(): void
    {
        $doc = NfseBuilder::make()
            ->ambiente(Ambiente::Homologacao)
            ->serie(1)
            ->competencia('2026-09-05')
            ->tomador($this->tomador())
            ->servico(new ServicoFiscal('010701', 'Desenvolvimento de software', codigoNbs: '112011000'))
            ->tributos($this->tributos())
            ->ibsCbs($this->ibsCbs())
            ->build();

        self::assertSame('1000.00', $doc->tributos->valorServicos);
        self::assertSame('112011000', $doc->servico->codigoNbs);
    }

    public function testSemIbsCbsAposPrazoFalha(): void
    {
        $this->expectException(ValidationException::class);
        NfseBuilder::make()
            ->tomador($this->tomador())
            ->servico(new ServicoFiscal('010701', 'Desenvolvimento de software', codigoNbs: '112011000'))
            ->tributos($this->tributos())
            ->build();
    }

    public function testCnbsInvalidoFalha(): void
    {
        $this->expectException(ValidationException::class);
        NfseBuilder::make()
            ->tomador($this->tomador())
            ->servico(new ServicoFiscal('010701', 'Desenvolvimento de software', codigoNbs: '1120110'))
            ->tributos($this->tributos())
            ->ibsCbs($this->ibsCbs())
            ->build();
    }

    public function testEnderecoTomadorIncompletoFalha(): void
    {
        $tomador = new Tomador(
            Cnpj::criar('11444777000161'),
            'Cliente',
            endereco: new Endereco(logradouro: 'Rua Sem Bairro', numero: '10', codigoMunicipioIbge: '3550308'),
        );

        $this->expectException(ValidationException::class);
        NfseBuilder::make()
            ->tomador($tomador)
            ->servico(new ServicoFiscal('010701', 'Serviço', codigoNbs: '112011000'))
            ->tributos($this->tributos())
            ->ibsCbs($this->ibsCbs())
            ->build();
    }
}
