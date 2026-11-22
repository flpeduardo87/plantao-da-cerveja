<?php

function deliveryRates(?string $stored = null): array {
    if ($stored !== null) {
        return json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
    }

    return [
        'Piedade' => 900,
        "Campo d'Água Verde" => 900,
        'Alto das Palmeiras' => 700,
        'Centro' => 800,
        'Boa Vista' => 900,
        'Industrial II' => 1000,
        'Alto da Tijuca' => 900,
        'Industrial 1 (Cristo Rei)' => 700,
        'Industrial 1 (Loteamento Santa Cruz / Cohab 1 / Cohab 4)' => 800,
        'Industrial 1 (Alto do Frigorífico)' => 900,
        'Sossego' => 700,
        'Água Verde' => 1000,
        'Cohab II' => 1000,
    ];
}
