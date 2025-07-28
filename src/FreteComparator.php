<?php

class FreteComparator {
    private $emissaoData = [];
    private $notasData = [];
    
    public function loadEmissaoCSV($filePath) {
        $this->emissaoData = [];
        
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            // Pular as primeiras 15 linhas (cabeçalho da empresa)
            for ($i = 0; $i < 15; $i++) {
                fgetcsv($handle, 0, ";");
            }
            
            // Ler o cabeçalho das colunas
            $header = fgetcsv($handle, 0, ";");
            
            // Ler os dados
            while (($data = fgetcsv($handle, 0, ";")) !== FALSE) {
                if (count($data) >= count($header)) {
                    $row = array_combine($header, $data);
                    if (!empty($row['CTE/NFSE']) && !empty($row['N. F.'])) {
                        $this->emissaoData[] = $row;
                    }
                }
            }
            fclose($handle);
        }
        
        return count($this->emissaoData);
    }
    
    public function loadNotasCSV($filePath) {
        $this->notasData = [];
        
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            // Ler o cabeçalho
            $header = fgetcsv($handle, 0, ",");
            
            // Ler os dados
            while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
                if (count($data) >= count($header)) {
                    $row = array_combine($header, $data);
                    if (!empty($row['Número']) && !empty($row['Compra'])) {
                        // Limpar o número da nota (remover pontos)
                        $numero = str_replace('.', '', $row['Número']);
                        // Converter valor de compra para float
                        $compra = str_replace(['"', ','], ['', '.'], $row['Compra']);
                        
                        $this->notasData[$numero] = [
                            'numero' => $numero,
                            'compra' => floatval($compra),
                            'fornecedor' => $row['Fornecedor']
                        ];
                    }
                }
            }
            fclose($handle);
        }
        
        return count($this->notasData);
    }
    
    /**
     * Normaliza o número da nota fiscal removendo pontos e espaços
     */
    private function normalizeNotaNumber($numero) {
        return str_replace(['.', ' '], '', trim($numero));
    }
    
    public function compareFretes() {
        $results = [];
        
        foreach ($this->emissaoData as $emissao) {
            $cte = $emissao['CTE/NFSE'];
            $notasFiscaisRaw = explode(',', $emissao['N. F.']);
            $nfValor = $this->parseValue($emissao['NF VALOR']);
            $freteTotal = $this->parseValue($emissao['FRETE TOTAL']);
            
            // Normalizar os números das notas fiscais do arquivo de emissão
            $notasFiscais = [];
            foreach ($notasFiscaisRaw as $nf) {
                $nf = trim($nf);
                if (!empty($nf)) {
                    $notasFiscais[] = $this->normalizeNotaNumber($nf);
                }
            }
            
            // Calcular soma das notas fiscais
            $somaNotas = 0;
            $notasEncontradas = [];
            $notasNaoEncontradas = [];
            
            foreach ($notasFiscais as $nf) {
                if (!empty($nf)) {
                    if (isset($this->notasData[$nf])) {
                        $somaNotas += $this->notasData[$nf]['compra'];
                        $notasEncontradas[] = [
                            'numero' => $nf,
                            'valor' => $this->notasData[$nf]['compra']
                        ];
                    } else {
                        $notasNaoEncontradas[] = $nf;
                    }
                }
            }
            
            // Verificar se NF VALOR bate com soma das notas
            $divergenciaNfValor = abs($nfValor - $somaNotas);
            $nfValorCorreto = $divergenciaNfValor < 0.01; // Tolerância de 1 centavo
            
            // Calcular frete esperado baseado no NF VALOR (se não há notas encontradas)
            // ou na soma das notas (se há notas encontradas)
            $valorBase = count($notasEncontradas) > 0 ? $somaNotas : $nfValor;
            $freteEsperado = $valorBase * 0.008; // 0.8% do valor base
            $divergenciaFrete = abs($freteTotal - $freteEsperado);
            $freteCorreto = $divergenciaFrete < 0.01; // Tolerância de 1 centavo
            
            // Determinar status
            $status = 'DIVERGENTE';
            $observacoes = [];
            
            if (count($notasNaoEncontradas) === 0 && count($notasEncontradas) > 0) {
                // Todas as notas foram encontradas
                if ($nfValorCorreto && $freteCorreto) {
                    $status = 'CORRETO';
                } else {
                    if (!$nfValorCorreto) {
                        $observacoes[] = 'Divergência no NF VALOR: esperado R$ ' . number_format($somaNotas, 2, ',', '.') . ', informado R$ ' . number_format($nfValor, 2, ',', '.');
                    }
                    if (!$freteCorreto) {
                        $observacoes[] = 'Divergência no FRETE: esperado R$ ' . number_format($freteEsperado, 2, ',', '.') . ', informado R$ ' . number_format($freteTotal, 2, ',', '.');
                    }
                }
            } else if (count($notasEncontradas) === 0) {
                // Nenhuma nota foi encontrada
                $observacoes[] = 'Nenhuma nota fiscal encontrada no relatório de notas';
                // Usar NF VALOR como base para cálculo do frete
                if ($freteCorreto) {
                    $observacoes[] = 'Frete calculado com base no NF VALOR está correto';
                    if ($nfValor > 0) {
                        $status = 'CORRETO'; // Se o frete está correto baseado no NF VALOR
                    }
                } else {
                    $observacoes[] = 'Divergência no FRETE baseado no NF VALOR: esperado R$ ' . number_format($freteEsperado, 2, ',', '.') . ', informado R$ ' . number_format($freteTotal, 2, ',', '.');
                }
            } else {
                // Algumas notas encontradas, outras não
                $observacoes[] = 'Apenas ' . count($notasEncontradas) . ' de ' . count($notasFiscais) . ' notas encontradas';
                $observacoes[] = 'Notas não encontradas: ' . implode(', ', $notasNaoEncontradas);
                
                // Verificar se o frete está correto baseado nas notas encontradas
                if ($freteCorreto) {
                    $observacoes[] = 'Frete calculado com base nas notas encontradas está correto';
                } else {
                    $observacoes[] = 'Divergência no FRETE: esperado R$ ' . number_format($freteEsperado, 2, ',', '.') . ', informado R$ ' . number_format($freteTotal, 2, ',', '.');
                }
            }
            
            $results[] = [
                'cte' => $cte,
                'notas_fiscais' => $notasFiscais,
                'notas_encontradas' => $notasEncontradas,
                'notas_nao_encontradas' => $notasNaoEncontradas,
                'nf_valor' => $nfValor,
                'soma_notas' => $somaNotas,
                'nf_valor_correto' => $nfValorCorreto,
                'divergencia_nf_valor' => $divergenciaNfValor,
                'frete_total' => $freteTotal,
                'frete_esperado' => $freteEsperado,
                'frete_correto' => $freteCorreto,
                'divergencia_frete' => $divergenciaFrete,
                'status' => $status,
                'observacoes' => $observacoes,
                'valor_base_calculo' => $valorBase
            ];
        }
        
        return $results;
    }
    
    private function parseValue($value) {
        // Remove espaços e converte vírgula para ponto
        $value = str_replace([' ', '.'], '', trim($value));
        $value = str_replace(',', '.', $value);
        return floatval($value);
    }
    
    public function getEmissaoData() {
        return $this->emissaoData;
    }
    
    public function getNotasData() {
        return $this->notasData;
    }
}

