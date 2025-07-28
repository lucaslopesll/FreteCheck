<?php

class FreteComparator {
    private $emissaoData = [];
    private $notasData = [];
    private $allProcessedNotas = [];
    
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
                    if (!empty($row['MINUTA']) && !empty($row['N. F.'])) {
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
                        // Converter valor de compra para float usando função específica para valores brasileiros
                        $compra = $this->parseBrazilianCurrency($row['Compra']);
                        
                        $this->notasData[$numero] = [
                            'numero' => $numero,
                            'compra' => $compra,
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
    
    /**
     * Converte valores monetários no formato brasileiro para float
     * Exemplos: "8.575,57" -> 8575.57, "1.234.567,89" -> 1234567.89, "123,45" -> 123.45
     */
    private function parseBrazilianCurrency($value) {
        // Remove aspas e espaços
        $value = str_replace(['"', ' '], '', trim($value));
        
        // Se o valor está vazio, retorna 0
        if (empty($value)) {
            return 0.0;
        }
        
        // Se tem vírgula, é o separador decimal brasileiro
        if (strpos($value, ',') !== false) {
            // Separa a parte inteira da decimal
            $parts = explode(',', $value);
            $integerPart = $parts[0];
            $decimalPart = isset($parts[1]) ? $parts[1] : '00';
            
            // Remove pontos da parte inteira (separadores de milhares)
            $integerPart = str_replace('.', '', $integerPart);
            
            // Reconstrói o número no formato americano
            $americanFormat = $integerPart . '.' . $decimalPart;
            
            return floatval($americanFormat);
        } else {
            // Se não tem vírgula, pode ser um número inteiro ou já estar no formato americano
            // Remove pontos que podem ser separadores de milhares
            $value = str_replace('.', '', $value);
            return floatval($value);
        }
    }
    
    public function compareFretes() {
        $this->allProcessedNotas = [];
        $results = [];
        
        foreach ($this->emissaoData as $emissao) {
            $minuta = $emissao['MINUTA'];
            $notasFiscaisRaw = explode(',', $emissao['N. F.']);
            $nfValor = $this->parseBrazilianCurrency($emissao['NF VALOR']);
            $freteTotal = $this->parseBrazilianCurrency($emissao['FRETE TOTAL']);
            
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

            // Lógica para detecção de notas fiscais duplicadas
            $isDuplicated = false;
            $currentDuplicatedNotas = [];
            foreach ($notasFiscais as $nf) {
                if (isset($this->allProcessedNotas[$nf])) {
                    $this->allProcessedNotas[$nf][] = $minuta;
                    $isDuplicated = true;
                } else {
                    $this->allProcessedNotas[$nf] = [$minuta];
                }
                // Check if this specific note is duplicated across any minuta
                if (count($this->allProcessedNotas[$nf]) > 1) {
                    $currentDuplicatedNotas[] = ['numero' => $nf, 'minutas' => $this->allProcessedNotas[$nf]];
                }
            }

            if ($isDuplicated) {
                $status = 'DUPLICADA';
                $observacoes[] = 'Nota(s) fiscal(is) desta minuta já apareceu(ram) em outra(s) minuta(s).';
            } else if (count($notasNaoEncontradas) === 0 && count($notasEncontradas) > 0) {
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
                'minuta' => $minuta,
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
                'valor_base_calculo' => $valorBase,
                'duplicated_notas' => $currentDuplicatedNotas
            ];
        }
        
        return $results;
    }
    
    public function getEmissaoData() {
        return $this->emissaoData;
    }
    
    public function getNotasData() {
        return $this->notasData;
    }
}

