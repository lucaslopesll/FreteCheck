<!DOCTYPE html>
<html lang="pt-br">
<head>
    <link rel="shortcut icon" type="image/x-icon" href="icone.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comparador de Frete</title>
    <link rel="stylesheet" href="modern_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="logo-farmacia.jpg" alt="Logo Farmácia Preço Popular">
            <h1>Comparador de Frete</h1>
        </div>
        <form action="upload.php" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="emissao_csv"><i class="fas fa-file-upload"></i> Relatório de Emissão de Notas (CSV):</label>
                <input type="file" name="emissao_csv" id="emissao_csv" accept=".csv" required>
            </div>
            <div class="form-group">
                <label for="notas_csv"><i class="fas fa-file-upload"></i> Relatório de Notas (CSV):</label>
                <input type="file" name="notas_csv" id="notas_csv" accept=".csv" required>
            </div>
            <button type="submit"><i class="fas fa-arrow-right"></i> Comparar Frete</button>
        </form>
    </div>
</body>
</html>