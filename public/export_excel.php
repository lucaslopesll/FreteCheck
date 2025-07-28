<?php
// export_excel.php

require_once __DIR__ . '/../vendor/autoload.php'; // Caminho para o autoload do Composer

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (isset($_POST['results_json'])) {
    $results = json_decode($_POST['results_json'], true);

    if (empty($results)) {
        echo 'Nenhum dado para exportar.';
        exit;
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Cabeçalhos
    $headers = [
        'Minuta', 'Status', 'NF Valor', 'Soma Notas', 'Frete Total', 
        'Frete Esperado', 'Divergência Frete', 'Observações', 
        'Notas Encontradas', 'Notas NÃO Encontradas', 'Valor Base para Cálculo'
    ];
    $sheet->fromArray([$headers], NULL, 'A1');

    // Dados
    $row = 2;
    foreach ($results as $result) {
        $observacoes = implode('; ', $result['observacoes'] ?? []);
        $notas_encontradas = implode('; ', array_map(function($n) { return 'NF ' . $n['numero'] . ': R$ ' . number_format($n['valor'], 2, ',', '.'); }, $result['notas_encontradas'] ?? []));
        $notas_nao_encontradas = implode('; ', $result['notas_nao_encontradas'] ?? []);

        $data = [
            $result['minuta'],
            $result['status'],
            $result['nf_valor'],
            $result['soma_notas'],
            $result['frete_total'],
            $result['frete_esperado'],
            $result['divergencia_frete'],
            $observacoes,
            $notas_encontradas,
            $notas_nao_encontradas,
            $result['valor_base_calculo']
        ];
        $sheet->fromArray([$data], NULL, 'A' . $row);
        $row++;
    }

    // Auto-ajustar largura das colunas
    foreach (range('A', $sheet->getHighestColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $writer = new Xlsx($spreadsheet);
    $fileName = 'comparacao_frete_' . date('Ymd_His') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');
    $writer->save('php://output');
    exit;
} else {
    echo 'Nenhum dado de resultado fornecido para exportação.';
}

?>

