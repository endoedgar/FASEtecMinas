<!DOCTYPE html>
<html lang="pt-br">
<head>
<title>Aguarde...</title>
<script src="portalLogin/js/jquery.min.js"></script>
<script src="portalLogin/js/jquery.redirect.js"></script>
<script src="portalLogin/js/crypto.js"></script>
<script src="portalLogin/js/diffiehellman.js"></script>
</head>
<body>
Aguarde enquanto a troca de chaves Diffie-Hellman é realizada.
<div id="status"></div>
<script>


var gestor = new GestorDiffieHellman();
gestor.negociarChaves('<?=base64_encode(hex2bin($gestorDH->obterChavePublicaServidorEmHexadecimal()))?>', '<?=base64_encode($_SESSION['iv'])?>');
gestor.enviarChavePublicaParaOServidor(<?=json_encode($_GET)?>);

</script>    
</body>
</html>                            