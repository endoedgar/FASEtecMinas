<?php
	require 'classes/gestorDiffieHellman.php';	
	session_name('loginWifi');
	session_start();
	
	$gestorDH = new GestorDiffieHellman();
	$gestorDH->inicializarGestor();
	
	if(isset($_POST['pntm'])) {
		$decodificacaoJson = json_decode($gestorDH->descriptografar(base64_decode($_POST['pntm'])), true);
		if(json_last_error() == 0) {
			$_REQUEST = $decodificacaoJson;
		} else {
			echo 'Falha JSON.';
			exit;
		}
	}
	
	if(isset($_POST['chavear'])) {
		header('Content-Type: application/json');
		$retornoJson = array();
		try {
			/*if(!isset($_SESSION['n']))
				$_SESSION['n']=0;
			if(!isset($_POST['chavePublica']) || !isset($_POST['mensagem']) || $_SESSION['n'] < 3) {
				$_SESSION['n']=intval($_SESSION['n']);
				$_SESSION['n']++;
				throw new Exception('FALHA DE AUTENTICAÇÃO ' . $_SESSION['n']);
			}*/
			
			$chavePublicaHexadecimalDoCliente = bin2hex(base64_decode($_POST['chavePublica']));
			
			$gestorDH->importarChavePublicaClienteEmHexadecimal($chavePublicaHexadecimalDoCliente);
			
			$retorno = $gestorDH->descriptografar(base64_decode($_POST['mensagem']));
			
			if($retorno == 'ALUNO_ETEC') {
				$retornoJson['cripto'] = $gestorDH->criptografar('SERVIDOR_ETEC');
			} else {
				throw new Exception('FALHA DE AUTENTICAÇÃO');
			}
		} catch(Exception $e) {
			header('HTTP/1.1 500 Internal Server Error');
			$retornoJson = array('mensagem' => $e->getMessage());
			$retornoJson['iv'] = base64_encode($_SESSION['iv']);
		}
		echo json_encode($retornoJson);
		exit;
	}
	
	if(isset($_GET['ue'])) {
		include 'keyexchange.php';
		/*$ec = new EC('curve25519');
		
		$chave_privada = '0e29446cfb7cf4864180adb755c385db197984f264887bd2dd70965a92e1cd16';
		//$chaveObtida = $ec->keyFromPrivate($_SESSION['curva_eliptica']['chavePrivada']);
		$chaveObtida = $ec->keyFromPrivate('0e29446cfb7cf4864180adb755c385db197984f264887bd2dd70965a92e1cd16', 'hex');
		
		echo 'Chave Privada: ' . $chaveObtida->getPrivate('hex') . '<br/>';
		echo 'Chave Pública: ' . $chaveObtida->getPublic()->encode('hex');
		
		//$parChave2 = $ec->genKeyPair();
		$parChave2 = $ec->keyFromPrivate('08dd943ab40f45ff81535530501270a65e700ca3bd1e49b415757ab652b0c948', 'hex');
		
		$a = $chaveObtida->derive($parChave2->getPublic());
		$b = $parChave2->derive($chaveObtida->getPublic());
		
		$comp = array($a, $b);
		
		print_r($comp);*/
		exit;
	}
	
	if(!isset($_REQUEST['clientip'])) {
		preg_match('/clientip=([0-9.]*)\&/', $_SERVER['QUERY_STRING'], $matches);
		if(count($matches) <= 1) {
			header('HTTP/1.1 500 Internal Server Error');
			exit;
		}
		$clientip=$matches[1];
	} else 
		$clientip=$_REQUEST['clientip'];

	if($_SERVER['REMOTE_ADDR'] != $clientip) {
		header('HTTP/1.1 500 Internal Server Error');
		exit;
	}

	$gatewayname = isset($_REQUEST['gatewayname']) ? $_REQUEST['gatewayname'] : '';

	if($gatewayname == '') {
		echo 'Nunca nem vi esse gateway.';
        header('HTTP/1.1 500 Internal Server Error');
        exit;
	}

	$redir = isset($_REQUEST['redir']) ? $_REQUEST['redir'] : '';
	$usuario = isset($_REQUEST['usuario']) ? trim($_REQUEST['usuario']) : '';
	$senha = isset($_REQUEST['senha']) ? $_REQUEST['senha'] : '';
	$msg = '';
	$authaction = isset($_REQUEST['authaction']) ? $_REQUEST['authaction'] : '';
	$token_ecc = isset($_REQUEST['token_ecc']) ? $_REQUEST['token_ecc'] : '';

	try {
		require_once 'classes/conector.class2.php';
		$con = new Conector();
		
		$dia_da_semana = date('D');
		
		if($dia_da_semana == 'Sat'){ // autorizacao automática de sábado (PRECISA MELHORAR ISSO)
			$con->autoriza($gatewayname, $clientip, 'AUTORIZACAO SABADAL', 'Login Autorizado Devido ao Sábado');
			header('Location: http://www.etecitapeva.com.br');
			exit;
		}
	
		if($_SERVER['REQUEST_METHOD'] == 'POST') {
			$subUsuario = $con->login($usuario, $senha, $gatewayname, $_SERVER['HTTP_USER_AGENT'], $clientip);
			if(isset($subUsuario['sucesso'])) {
				if($subUsuario['sucesso'] == 1) {
					$tok = $subUsuario['dadosClienteNDS']['token'];
					$mensagem = $subUsuario['mensagem'];
					$hostRetorno = $subUsuario['hostRetorno'];
					$subUsuario = $subUsuario['dadosLogin'];
					$tipoImagem = gettype($subUsuario['foto']);
					if($tipoImagem == 'resource') {
						$base64Imagem = base64_encode(stream_get_contents($subUsuario['foto']));
					} else {
						$base64Imagem = '';
					}
					$authaction = 'http://'.$hostRetorno.':2050/nodogsplash_auth/?clientip='.$clientip;
		
					$linkOk = $authaction.'&tok='.$tok;
					include 'boasVindas.php';
					exit;
				} else {
					$msg = $subUsuario['mensagem'];
				}
			} else {
				$msg = 'Houve uma falha incomum.';
			}
		}
	} catch(Exception $e) {
		$msg = 'Exceção:'. $e->getMessage();
	}
	
	include "formLogin.php";
?>
