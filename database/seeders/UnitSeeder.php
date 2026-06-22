<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['description' => 'Unidad(es)', 'abbreviation' => 'NIU', 'type' => 'Otros', 'is_sunat' => true],
            ['description' => 'Gramo(s)', 'abbreviation' => 'GRM', 'type' => 'Masa', 'is_sunat' => true],
            ['description' => 'Kilogramo(s)', 'abbreviation' => 'KGM', 'type' => 'Masa', 'is_sunat' => true],
            ['description' => 'Caja(s)', 'abbreviation' => 'BX', 'type' => 'Otros', 'is_sunat' => true],
            ['description' => 'Paquete(s)', 'abbreviation' => 'PK', 'type' => 'Otros', 'is_sunat' => true],
            ['description' => 'Medio(s)', 'abbreviation' => 'Medio(s)', 'type' => 'Masa', 'is_sunat' => false],
            ['description' => 'Jarra(s)', 'abbreviation' => 'Jarra(s)', 'type' => 'Masa', 'is_sunat' => false],
            ['description' => 'Media(s) jarra(s)', 'abbreviation' => '1/2 jarra(s)', 'type' => 'Masa', 'is_sunat' => false],
            ['description' => 'Vaso(s)', 'abbreviation' => 'Vaso(s)', 'type' => 'Masa', 'is_sunat' => false],
            ['description' => 'Litro(s)', 'abbreviation' => 'LTR', 'type' => 'Masa', 'is_sunat' => true],
            ['description' => 'Botella(s)', 'abbreviation' => 'BO', 'type' => 'Otros', 'is_sunat' => true],
            ['description' => 'Galón(es)', 'abbreviation' => 'GLI', 'type' => 'Otros', 'is_sunat' => true],
            ['description' => 'Onza(s)', 'abbreviation' => 'ONZ', 'type' => 'Otros', 'is_sunat' => true],
            ['description' => 'Rollo(s)', 'abbreviation' => 'Rollo(s)', 'type' => 'Otros', 'is_sunat' => false],
            ['description' => 'Porción(es)', 'abbreviation' => 'Porción(es)', 'type' => 'Masa', 'is_sunat' => false],
            ['description' => 'Mililitro(s)', 'abbreviation' => 'MLT', 'type' => 'Masa', 'is_sunat' => true],
            ['description' => 'Servicio(s)', 'abbreviation' => 'ZZ', 'type' => 'Otros', 'is_sunat' => true],
            ['description' => 'Kit(s)', 'abbreviation' => 'KT', 'type' => 'Otros', 'is_sunat' => true],
            ['description' => 'Metro(s)', 'abbreviation' => 'MTR', 'type' => 'Longitud', 'is_sunat' => true],
            ['description' => 'Par(es)', 'abbreviation' => 'PR', 'type' => 'Otros', 'is_sunat' => true],
            ['description' => 'Metro(s) cúbico(s)', 'abbreviation' => 'MTQ', 'type' => 'Longitud', 'is_sunat' => true],
            ['description' => 'Tonelada(s)', 'abbreviation' => 'TNE', 'type' => 'Masa', 'is_sunat' => true],
            ['description' => 'Bolsa(s)', 'abbreviation' => 'BG', 'type' => 'Otros', 'is_sunat' => true],
            ['description' => 'Balde(s)', 'abbreviation' => 'BJ', 'type' => 'Otros', 'is_sunat' => true],
            ['description' => 'Juego(s)', 'abbreviation' => 'SET', 'type' => 'Otros', 'is_sunat' => true],
            ['description' => 'Display', 'abbreviation' => 'Display', 'type' => 'Otros', 'is_sunat' => false],
            ['description' => 'Táper(es)', 'abbreviation' => 'Táper(es)', 'type' => 'Otros', 'is_sunat' => false],
            ['description' => 'Tira(s)', 'abbreviation' => 'Tira(s)', 'type' => 'Otros', 'is_sunat' => false],
            ['description' => 'Six-pack(s)', 'abbreviation' => 'Six-pack(s)', 'type' => 'Otros', 'is_sunat' => false],
            ['description' => 'Saco(s)', 'abbreviation' => 'Saco(s)', 'type' => 'Otros', 'is_sunat' => false],
            ['description' => 'Bandeja(s)', 'abbreviation' => 'Bandeja(s)', 'type' => 'Otros', 'is_sunat' => false],
            ['description' => 'Docena(s)', 'abbreviation' => 'DZN', 'type' => 'Otros', 'is_sunat' => true],
            ['description' => 'Arroba(s)', 'abbreviation' => 'Arroba(s)', 'type' => 'Otros', 'is_sunat' => false],
            ['description' => 'Quintal(es)', 'abbreviation' => 'Quintal(es)', 'type' => 'Otros', 'is_sunat' => false],
            ['description' => 'Plancha(s)', 'abbreviation' => 'Plancha(s)', 'type' => 'Otros', 'is_sunat' => false],
            ['description' => 'Media(s) caja(s)', 'abbreviation' => '1/2 caja(s)', 'type' => 'Otros', 'is_sunat' => false],
            ['description' => 'Medio(s) saco(s)', 'abbreviation' => 'Medio(s) saco(s)', 'type' => 'Otros', 'is_sunat' => false],
            ['description' => 'Bidón(es)', 'abbreviation' => 'Bidón(es)', 'type' => 'Otros', 'is_sunat' => false],
            ['description' => 'Media(s) bolsa(s)', 'abbreviation' => '1/2 bolsa(s)', 'type' => 'Otros', 'is_sunat' => false],
            ['description' => 'Cajetilla(s)', 'abbreviation' => 'Cajetilla(s)', 'type' => 'Otros', 'is_sunat' => false],
            ['description' => 'Ciento(s)', 'abbreviation' => 'CEN', 'type' => 'Otros', 'is_sunat' => true],
            ['description' => 'Millar(es)', 'abbreviation' => 'MIL', 'type' => 'Otros', 'is_sunat' => true],
            ['description' => 'Fardo(s)', 'abbreviation' => 'BE', 'type' => 'Otros', 'is_sunat' => true],
            ['description' => 'Medio(s) kilogramo(s)', 'abbreviation' => '1/2 kilogramo(s)', 'type' => 'Masa', 'is_sunat' => false],
            ['description' => 'Cuarto(s) de docena(s)', 'abbreviation' => 'Cuarto(s) de docena(s)', 'type' => 'Otros', 'is_sunat' => false],
            ['description' => 'Varilla(s)', 'abbreviation' => 'Varilla(s)', 'type' => 'Otros', 'is_sunat' => false],
            ['description' => 'Media(s) docena(s)', 'abbreviation' => 'Media(s) docena(s)', 'type' => 'Otros', 'is_sunat' => false],
            ['description' => 'Mitad(es)', 'abbreviation' => 'Mitad(es)', 'type' => 'Otros', 'is_sunat' => false],
            ['description' => 'Tercio(s)', 'abbreviation' => 'Tercio(s)', 'type' => 'Otros', 'is_sunat' => false],
            ['description' => 'Cuarto(s)', 'abbreviation' => 'Cuarto(s)', 'type' => 'Otros', 'is_sunat' => false],
            ['description' => 'Octavo(s)', 'abbreviation' => 'Octavo(s)', 'type' => 'Otros', 'is_sunat' => false],
            ['description' => 'Tableta(s)', 'abbreviation' => 'Tableta(s)', 'type' => 'Otros', 'is_sunat' => false],
            ['description' => 'Barra(s)', 'abbreviation' => 'Barra(s)', 'type' => 'Otros', 'is_sunat' => false],
        ];

        foreach ($units as $unit) {
            Unit::updateOrCreate(
                [
                    'description' => $unit['description'],
                    'abbreviation' => $unit['abbreviation'],
                ],
                [
                    'type' => $unit['type'],
                    'is_sunat' => $unit['is_sunat'],
                ]
            );
        }
    }
}
