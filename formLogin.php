<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login</title>
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
					<img src="portalLogin/logoEtecItapeva.png" alt="Avatar">
				</div>				
				<h4 class="modal-title">Login Etec</h4>
                <hr/>
                <h5>Use seu RM e senha do NSA para logar.</h5>
				<h5>Se você conectar usando o mesmo RM com dispositivos diferentes, suas conexões anteriores serão descartadas na Escola inteira.</h5>
			</div>
			<div class="modal-body">
				<form action=<?=$_SERVER['PHP_SELF']?> method="post" id="loginForm">
					<input type="hidden" name="gatewayname" value="<?=htmlentities($gatewayname)?>" />
					<input type="hidden" name="clientip" value="<?=htmlentities($clientip)?>" />
					<input type="hidden" name="redir" value="<?=htmlentities($redir)?>" />
					<input type="hidden" id="chavePublicaSessao" name="chavePublicaSessao" value="<?=base64_encode(hex2bin($chave_publica_sessao))?>" />
					<?php if(isset($msg) && strlen($msg) > 0) { ?>
					<div class="alert alert-warning" role="alert">
						<?=htmlentities($msg)?>
					</div>
					<?php } ?>
					<div class="form-group">
						<input type="text" class="form-control" name="usuario" placeholder="RM" required="required" value="<?=htmlentities($usuario)?>" maxlength="50">		
					</div>
					<div class="form-group">
						<input type="password" class="form-control" name="senha" placeholder="Senha" required="required" maxlength="50">	
					</div>        
					<div class="form-group">
						<button type="submit" id="botaoEnvio" class="btn btn-primary btn-lg btn-block login-btn">Login</button>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<?=htmlentities($gatewayname)?>
			</div>
		</div>
	</div>
</div> 
<script>
$('#loginForm').submit(function(event) {
 var botaoEnvio = $('#botaoEnvio'); 
 event.preventDefault();
 
 /*var data = JSON.stringify( $('#loginForm').serializeArray() );
 encriptografaParaBase64( data );*/
 
 botaoEnvio.disabled=true;
 botaoEnvio.innerText='Logando...';

 $(this).unbind('submit').submit();
})


</script>    
</body>
</html>                            