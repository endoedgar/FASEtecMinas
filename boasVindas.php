<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Boas-vindas</title>
<link href="portalLogin/css/css.css" rel="stylesheet">
<link rel="stylesheet" href="portalLogin/css/font-awesome.min.css">
<link rel="stylesheet" href="portalLogin/css/bootstrap.min.css">
<script src="portalLogin/js/jquery.min.js"></script>
<script src="portalLogin/js//bootstrap.min.js"></script>
<link href="portalLogin/css/custom.css" rel="stylesheet">
<!--
	Developado por Edgar de Jesus Endo Junior
	Usei NoDogSplash + PHP para integrar o banco de dados do NSA com o Wifi
	Todos os routers estão com o Openwrt instalado e uma versão dos binários do NoDogSplash
	
	Python foi necessário para manter as conexões SSH com os roteadores abertas para melhorar
	a performance de acesso, pois é mais fácil esperar o Palmeiras ganhar um mundial do que 
	iniciar uma nova conexão SSH do zero.
-->
</head>
<body>
<div class="modalPrincipal">
	<div class="modal-dialog modal-login">
		<div class="modal-content">
			<div class="modal-header">
				<div class="avatar">
					<img src="data:image/jpeg;charset=utf-8;base64, <?=$base64Imagem?>" />
				</div>				
				<h4 class="modal-title">Login Etec</h4>
                <hr/>
                <h4><?=$mensagem?></h4>
				<h4>Clique <a href="http://www.etecitapeva.com.br">aqui</a> para continuar</h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
			</div>
		</div>
	</div>
</div>     
</body>
<script>
     		    setTimeout(function(){
     		       window.location.href = 'http://www.etecitapeva.com.br';
     		    }, 5000);
</script>
</html>                            