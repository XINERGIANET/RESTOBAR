<?php

use App\Services\ApisunatService;

function invokeApisunatValidator(array $documentBody): void
{
    $method = new ReflectionMethod(ApisunatService::class, 'validateDocumentBodyForSunat');
    $method->setAccessible(true);
    $method->invoke(new ApisunatService, $documentBody);
}

function apisunatDocumentBodyWithLine(float $grossUnitPrice, float $lineSubtotal): array
{
    return [
        'cac:InvoiceLine' => [[
            'cbc:LineExtensionAmount' => [
                '_attributes' => ['currencyID' => 'PEN'],
                '_text' => $lineSubtotal,
            ],
            'cac:PricingReference' => [
                'cac:AlternativeConditionPrice' => [
                    'cbc:PriceAmount' => [
                        '_attributes' => ['currencyID' => 'PEN'],
                        '_text' => $grossUnitPrice,
                    ],
                    'cbc:PriceTypeCode' => ['_text' => '01'],
                ],
            ],
            'cac:TaxTotal' => [
                'cbc:TaxAmount' => [
                    '_attributes' => ['currencyID' => 'PEN'],
                    '_text' => 1.8,
                ],
                'cac:TaxSubtotal' => [[
                    'cbc:TaxableAmount' => [
                        '_attributes' => ['currencyID' => 'PEN'],
                        '_text' => $lineSubtotal,
                    ],
                    'cbc:TaxAmount' => [
                        '_attributes' => ['currencyID' => 'PEN'],
                        '_text' => 1.8,
                    ],
                    'cac:TaxCategory' => [
                        'cbc:Percent' => ['_text' => 18],
                        'cbc:TaxExemptionReasonCode' => ['_text' => '10'],
                        'cac:TaxScheme' => [
                            'cbc:ID' => ['_text' => '1000'],
                            'cbc:Name' => ['_text' => 'IGV'],
                            'cbc:TaxTypeCode' => ['_text' => 'VAT'],
                        ],
                    ],
                ]],
            ],
        ]],
        'cac:TaxTotal' => [
            'cac:TaxSubtotal' => [
                'cac:TaxCategory' => [
                    'cac:TaxScheme' => [
                        'cbc:ID' => ['_text' => '1000'],
                    ],
                ],
            ],
        ],
    ];
}

test('apisunat validator rejects invoice lines with zero amounts', function () {
    invokeApisunatValidator(apisunatDocumentBodyWithLine(0, 0));
})->throws(RuntimeException::class, 'importe cero');

test('apisunat validator accepts invoice lines with valid igv tax data', function () {
    invokeApisunatValidator(apisunatDocumentBodyWithLine(11.8, 10));

    expect(true)->toBeTrue();
});
