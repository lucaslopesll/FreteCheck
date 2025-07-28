<?php
require_once '../src/FreteComparator_fixed_v2.php';

$error = '';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar se os arquivos foram enviados
    if (!isset($_FILES['emissao_csv']) || !isset($_FILES['notas_csv'])) {
        $error = 'Por favor, selecione ambos os arquivos CSV.';
    } else {
        $emissaoFile = $_FILES['emissao_csv'];
        $notasFile = $_FILES['notas_csv'];
        
        // Verificar se não houve erro no upload
        if ($emissaoFile['error'] !== UPLOAD_ERR_OK || $notasFile['error'] !== UPLOAD_ERR_OK) {
            $error = 'Erro no upload dos arquivos.';
        } else {
            try {
                $comparator = new FreteComparator();
                
                // Carregar os arquivos CSV
                $emissaoCount = $comparator->loadEmissaoCSV($emissaoFile['tmp_name']);
                $notasCount = $comparator->loadNotasCSV($notasFile['tmp_name']);
                
                if ($emissaoCount === 0) {
                    $error = 'Nenhum dado válido encontrado no arquivo de emissão de notas.';
                } elseif ($notasCount === 0) {
                    $error = 'Nenhum dado válido encontrado no arquivo de relatório de notas.';
                } else {
                    // Realizar a comparação
                    $results = $comparator->compareFretes();
                }
                
            } catch (Exception $e) {
                $error = 'Erro ao processar os arquivos: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <link rel="shortcut icon" type="image/x-icon" href="icone.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultados da Comparação - Comparador de Frete</title>
    <link rel="stylesheet" href="modern_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="logo-farmacia.jpg" alt="Logo Farmácia Preço Popular">
            <h1>Resultados da Comparação de Frete</h1>
        </div>
        
        <?php if ($error): ?>
            <div class="result-item divergente">
                <div class="status divergente"><i class="fas fa-exclamation-circle"></i> ERRO</div>
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($results)): ?>
            <div class="results">
                <h2><i class="fas fa-chart-bar"></i> Resumo dos Resultados</h2>
                <div class="summary-box">
                    <p><strong>Total de Minutas analisados:</strong> <?php echo count($results); ?></p>
                    <p><i class="fas fa-check-circle correct-icon"></i> <strong>Minutas corretas:</strong> <?php echo count(array_filter($results, function($r) { return $r['status'] === 'CORRETO'; })); ?></p>
                    <p><i class="fas fa-exclamation-triangle divergent-icon"></i> <strong>Minutas divergentes:</strong> <?php echo count(array_filter($results, function($r) { return $r['status'] === 'DIVERGENTE'; })); ?></p>
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Minuta</th>
                                <th>Status</th>
                                <th>NF Valor</th>
                                <th>Soma Notas</th>
                                <th>Frete Total</th>
                                <th>Frete Esperado</th>
                                <th>Divergência Frete</th>
                                <th>Detalhes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $result): ?>
                                <tr class="<?php echo strtolower($result['status']); ?>">
                                    <td><?php echo htmlspecialchars($result['minuta']); ?></td>
                                    <td>
                                        <span class="status <?php echo strtolower($result['status']); ?>">
                                            <?php 
                                                if ($result['status'] === 'CORRETO') {
                                                    echo '<i class="fas fa-check-circle"></i> CORRETO';
                                                } elseif ($result['status'] === 'DIVERGENTE') {
                                                    echo '<i class="fas fa-exclamation-triangle"></i> DIVERGENTE';
                                                } elseif ($result['status'] === 'DUPLICADA') { // Adicionado status DUPLICADA
                                                    echo '<i class="fas fa-exclamation-circle"></i> DUPLICADA';
                                                }
                                            ?>
                                        </span>
                                    </td>
                                    <td>R$ <?php echo number_format($result['nf_valor'], 2, ',', '.'); ?></td>
                                    <td>R$ <?php echo number_format($result['soma_notas'], 2, ',', '.'); ?></td>
                                    <td>R$ <?php echo number_format($result['frete_total'], 2, ',', '.'); ?></td>
                                    <td>R$ <?php echo number_format($result['frete_esperado'], 2, ',', '.'); ?></td>
                                    <td>
                                        <?php if ($result['divergencia_frete'] > 0.01): ?>
                                            <span style="color: var(--danger-color);">
                                                R$ <?php echo number_format($result['divergencia_frete'], 2, ',', '.'); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--success-color);"><i class="fas fa-check"></i> OK</span>
                                        <?php endif; ?>
                                    </td>
                            <td>
                                <button class="details-button" onclick="toggleDetails('details-<?php echo $result['minuta']; ?>')">
                                    <i class="fas fa-info-circle"></i> Ver Detalhes
                                </button>
                            </td>
                        </tr>
                        <tr id="details-<?php echo $result['minuta']; ?>" style="display: none;">
                            <td colspan="8">
                                <div class="details-section">
                                    <h4><i class="fas fa-file-invoice"></i> Detalhes do minuta <?php echo htmlspecialchars($result['minuta']); ?></h4>
                                    
                                    <?php if (!empty($result['observacoes'])): ?>
                                        <h5><i class="fas fa-comment-dots"></i> Observações:</h5>
                                        <ul>
                                            <?php foreach ($result['observacoes'] as $obs): ?>
                                                <li style="color: var(--warning-color);"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($obs); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    
                                    <h5><i class="fas fa-receipt"></i> Notas Fiscais Encontradas (<?php echo count($result['notas_encontradas']); ?>):</h5>
                                    <?php if (!empty($result['notas_encontradas'])): ?>
                                        <ul>
                                            <?php foreach ($result['notas_encontradas'] as $nota): ?>
                                                <li><i class="fas fa-check-circle" style="color: var(--success-color);"></i> NF <?php echo $nota['numero']; ?>: R$ <?php echo number_format($nota['valor'], 2, ',', '.'); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p><i class="fas fa-times-circle" style="color: var(--danger-color);"></i> Nenhuma nota fiscal encontrada no relatório de notas.</p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($result['notas_nao_encontradas'])): ?>
                                        <h5 style="color: var(--danger-color);"><i class="fas fa-exclamation-triangle"></i> Notas Fiscais NÃO Encontradas (<?php echo count($result['notas_nao_encontradas']); ?>):</h5>
                                        <ul>
                                            <?php foreach ($result['notas_nao_encontradas'] as $nota): ?>
                                                <li style="color: var(--danger-color);"><i class="fas fa-times-circle"></i> NF <?php echo $nota; ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($result["duplicated_notas"])): ?>
                                        <h5 style="color: var(--danger-color);"><i class="fas fa-copy"></i> Notas Fiscais Duplicadas:</h5>
                                        <ul>
                                            <?php foreach ($result["duplicated_notas"] as $dup_nota): ?>
                                                <li style="color: var(--danger-color);"><i class="fas fa-exclamation-circle"></i> NF <?php echo $dup_nota["numero"]; ?> (Minutas: <?php echo implode(", ", $dup_nota["minutas"]); ?>)</li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    
                                    <h5><i class="fas fa-calculator"></i> Cálculos:</h5>
                                    <p><strong>NF Valor (informado):</strong> R$ <?php echo number_format($result['nf_valor'], 2, ',', '.'); ?></p>
                                    <p><strong>Soma das Notas Encontradas:</strong> R$ <?php echo number_format($result['soma_notas'], 2, ',', '.'); ?></p>
                                    <p><strong>Valor Base para Cálculo:</strong> R$ <?php echo number_format($result['valor_base_calculo'], 2, ',', '.'); ?></p>
                                    <p><strong>Frete Esperado (0.8% do valor base):</strong> R$ <?php echo number_format($result['frete_esperado'], 2, ',', '.'); ?></p>
                                    <p><strong>Frete Informado:</strong> R$ <?php echo number_format($result['frete_total'], 2, ',', '.'); ?></p>
                                    
                                    <?php if (!$result['nf_valor_correto'] && $result['soma_notas'] > 0): ?>
                                        <p style="color: var(--danger-color);">
                                            <strong><i class="fas fa-exclamation-circle"></i> Divergência NF Valor:</strong> 
                                            R$ <?php echo number_format($result['divergencia_nf_valor'], 2, ',', '.'); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if (!$result['frete_correto']): ?>
                                        <p style="color: var(--danger-color);">
                                            <strong><i class="fas fa-exclamation-circle"></i> Divergência Frete:</strong> 
                                            R$ <?php echo number_format($result['divergencia_frete'], 2, ',', '.'); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 30px; text-align: center;">
            <a href="index.php" class="nav-link">
                <i class="fas fa-redo"></i> Nova Comparação
            </a>
            <br>
            <br>
            <button onclick="exportToExcel()" class="nav-link" style="margin-left: 10px;">
                <i class="fas fa-file-excel"></i> Exportar para Excel
            </button>
            <br>
            <br>
            <button onclick="window.print()" class="nav-link" style="margin-left: 10px;">
                <i class="fas fa-print"></i> Imprimir Relatório
            </button>
        </div>
    </div>
    
    <script>
        function toggleDetails(id) {
            var element = document.getElementById(id);
            if (element.style.display === 'none') {
                element.style.display = 'table-row';
            } else {
                element.style.display = 'none';
            }
        }

        function exportToExcel() {
            var results = <?php echo json_encode($results); ?>;
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'export_excel.php'; // Certifique-se de que este arquivo existe e lida com a exportação
            form.target = '_blank';

            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'results_json';
            input.value = JSON.stringify(results);
            form.appendChild(input);

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
    </script>
</body>
</html>