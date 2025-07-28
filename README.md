# Sistema de Comparação de Frete

## Descrição

Este sistema foi desenvolvido para comparar valores de frete entre dois arquivos CSV:
- **Relatório de Emissão de Notas**: Contém informações sobre CTEs e valores de frete
- **Relatório de Notas**: Contém informações detalhadas sobre notas fiscais e seus valores

O sistema identifica divergências nos cálculos de frete e apresenta os resultados de forma clara e organizada.

## Funcionalidades

- **Upload de Arquivos CSV**: Interface web para envio dos dois arquivos necessários
- **Comparação Automática**: Análise automática dos dados e cálculo de divergências
- **Relatório Detalhado**: Apresentação dos resultados com status (CORRETO/DIVERGENTE)
- **Detalhes Expandíveis**: Informações detalhadas sobre cada CTE analisado
- **Tratamento de Dados Ausentes**: Sistema funciona mesmo quando notas não são encontradas

## Regras de Negócio

1. **Cálculo do Frete**: O frete esperado é calculado como 99,2% do valor base (desconto de 0,8%)
2. **Valor Base**: 
   - Se todas as notas fiscais forem encontradas: usa a soma das notas
   - Se nenhuma nota for encontrada: usa o valor NF VALOR informado
3. **Tolerância**: Divergências menores que R$ 0,01 são consideradas corretas
4. **Status**:
   - **CORRETO**: Quando não há divergências significativas
   - **DIVERGENTE**: Quando há diferenças nos valores calculados

## Estrutura do Projeto

```
frete_checker/
├── public/
│   ├── index.php          # Página inicial com formulário de upload
│   ├── upload.php         # Processamento e exibição de resultados
│   └── style.css          # Estilos da interface
├── src/
│   └── FreteComparator.php # Classe principal para comparação
├── test_files.php         # Script de teste via linha de comando
└── README.md              # Esta documentação
```

## Requisitos do Sistema

- **PHP 8.1+** com suporte a CLI
- **Servidor Web** (Apache, Nginx ou servidor embutido do PHP)
- **Navegador Web** moderno para acessar a interface

## Instalação

1. **Clone ou copie os arquivos** para o diretório desejado
2. **Instale o PHP** se não estiver instalado:
   ```bash
   sudo apt update
   sudo apt install php php-cli
   ```
3. **Inicie o servidor** (para desenvolvimento):
   ```bash
   cd frete_checker/public
   php -S 0.0.0.0:8080
   ```
4. **Acesse o sistema** em: http://localhost:8080

## Como Usar

### Via Interface Web

1. **Acesse** o sistema no navegador
2. **Selecione** o arquivo "Relatório de Emissão de Notas (CSV)"
3. **Selecione** o arquivo "Relatório de Notas (CSV)"
4. **Clique** em "Comparar Frete"
5. **Analise** os resultados apresentados
6. **Clique** em "Ver Detalhes" para informações específicas de cada CTE

### Via Linha de Comando

```bash
cd frete_checker
php test_files.php
```

## Formato dos Arquivos CSV

### Relatório de Emissão de Notas
- **Separador**: Ponto e vírgula (;)
- **Codificação**: Latin-1
- **Estrutura**: Arquivo com cabeçalho da empresa nas primeiras 15 linhas
- **Campos importantes**:
  - CTE/NFSE: Identificador do CTE
  - N. F.: Números das notas fiscais (separados por vírgula)
  - NF VALOR: Valor total das notas
  - FRETE TOTAL: Valor do frete cobrado

### Relatório de Notas
- **Separador**: Vírgula (,)
- **Codificação**: UTF-8
- **Campos importantes**:
  - Número: Número da nota fiscal
  - Compra: Valor da compra (entre aspas, com vírgula decimal)

## Interpretação dos Resultados

### Tabela Principal
- **CTE/NFSE**: Identificador do conhecimento de transporte
- **Status**: CORRETO ou DIVERGENTE
- **NF Valor**: Valor informado no relatório de emissão
- **Soma Notas**: Soma dos valores das notas encontradas
- **Frete Total**: Valor do frete informado
- **Frete Esperado**: Valor calculado (99,2% do valor base)
- **Divergência Frete**: Diferença entre frete informado e esperado

### Detalhes Expandíveis
- **Observações**: Informações sobre o processamento
- **Notas Encontradas**: Lista das notas fiscais localizadas no relatório
- **Notas NÃO Encontradas**: Lista das notas que não foram localizadas
- **Cálculos**: Detalhamento dos valores utilizados

## Tratamento de Problemas Comuns

### Notas Fiscais Não Encontradas
- **Causa**: Números de notas no arquivo de emissão não existem no relatório de notas
- **Solução**: O sistema usa o NF VALOR como base para cálculo
- **Observação**: Pode indicar arquivos de períodos diferentes

### Divergências de Codificação
- **Causa**: Arquivos com codificações diferentes
- **Solução**: Sistema trata automaticamente Latin-1 e UTF-8

### Valores com Formatação Diferente
- **Causa**: Vírgulas, pontos e aspas nos valores
- **Solução**: Sistema normaliza automaticamente os valores

## Limitações

1. **Formato Fixo**: Arquivos devem seguir o formato específico descrito
2. **Tolerância**: Divergências menores que R$ 0,01 são ignoradas
3. **Dependência de Dados**: Resultados dependem da qualidade dos dados de entrada

## Suporte Técnico

Para problemas ou dúvidas:
1. Verifique se os arquivos estão no formato correto
2. Confirme se o PHP está instalado e funcionando
3. Verifique as permissões de arquivo se houver erro de upload
4. Use o script de teste via linha de comando para debug

---

**Desenvolvido para análise e comparação de valores de frete com base em dados de CTEs e notas fiscais.**

