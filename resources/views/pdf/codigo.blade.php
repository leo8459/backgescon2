<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Códigos de Barras</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 20px;
        }
        .codigo-container {
            display: inline-block;
            width: 45%;
            margin: 10px;
            text-align: center;
            border: 1px solid #ddd;
            padding: 10px;
        }
        .barcode-img {
            width: 200px;
            height: auto;
        }
        .codigo-text {
            font-size: 18px;
            font-weight: bold;
            margin-top: 5px;
        }
    </style>
</head>
<body>

    <h2>Código de Barras Generado</h2>

    @for ($i = 0; $i < 4; $i++)
    <div class="codigo-container">
        <img src="data:image/png;base64,{{ $codigo->barcode }}" class="barcode-img">
        <p class="codigo-text">{{ $codigo->codigo }}</p>
    </div>
    @endfor

</body>
</html>
