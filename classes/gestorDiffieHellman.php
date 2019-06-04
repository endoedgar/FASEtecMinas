<?php
	require 'crypto/vendor/autoload.php';
	use Elliptic\EC;
	
	class GestorDiffieHellman {
		private $ec;
		private $chaveServidor;
		private $chaveCliente;
		private $chaveCompartilhadaAES;
		
		public function __construct() {
			$this->ec = new EC('curve25519');
		}
		
		public function inicializarGestor() {
			if(!$this->possuiChavePrivadaServidorNaSessao()) {
				$this->gerarChavePrivadaServidorNaSessao();
			}
	
			if($this->possuiChavePrivadaServidorNaSessao()) {
				$this->importarChavePrivadaServidorEmHexadecimal($_SESSION['curva_eliptica']['chavePrivada']);
			}
	
			if(!$this->possuiIV()) {
				$this->gerarNovoIV();
			}
			
			if($this->possuiChaveCompartilhadaAES())
				$this->chaveCompartilhadaAES = $_SESSION['chaveCompartilhadaAES'];
		}
		
		public function possuiChaveCompartilhadaAES() {
			return isset($_SESSION['chaveCompartilhadaAES']);
		}
		
		public function possuiIV() {
			return isset($_SESSION['iv']);
		}
		
		public function gerarNovoIV() {
			$_SESSION['iv'] = random_bytes(16);
		}
		
		public function possuiChavePrivadaServidorNaSessao() {
			return isset($_SESSION['curva_eliptica']);
		}
		
		public function gerarChavePrivadaServidorNaSessao() {
			$this->gerarNovaChaveServidor();
			$_SESSION['curva_eliptica'] = array(
				'chavePrivada' => $this->obterChavePrivadaServidorEmHexadecimal()
			);
		}
		
		public function importarChavePrivadaServidorEmHexadecimal($chavePrivadaHexadecimal) {
			$this->chaveServidor = $this->ec->keyFromPrivate($chavePrivadaHexadecimal);
		}
		
		public function importarChavePublicaClienteEmHexadecimal($chavePublicaHexadecimal) {
			$this->chaveCliente = $this->ec->keyFromPublic($chavePublicaHexadecimal, 'hex');
			
			$_SESSION['curva_eliptica_cliente'] = array(
				'chavePublica' => $chavePublicaHexadecimal
			);
			
			$_SESSION['chaveCompartilhadaAES'] = hex2bin(hash('sha256', $this->chaveServidor->derive($this->chaveCliente->getPublic())->toString(16)));
			
			$this->chaveCompartilhadaAES = $_SESSION['chaveCompartilhadaAES'];
		}
		
		public function obterChaveCompartilhadaAES() {
			return $this->chaveCompartilhadaAES;
		}
		
		public function gerarNovaChaveServidor() {
			$this->chaveServidor = $this->ec->genKeyPair();
		}
		
		public function obterChavePrivadaServidorEmHexadecimal() {
			return $this->chaveServidor->getPrivate('hex');
		}
		
		public function obterChavePublicaServidorEmHexadecimal() {
			 return $this->chaveServidor->getPublic()->encode('hex');
		}
		
		public function descriptografar($mensagem) {
			return openssl_decrypt($mensagem, 'aes-256-ctr', $this->chaveCompartilhadaAES, OPENSSL_RAW_DATA, $_SESSION['iv']);
		}
		
		public function criptografar($mensagem) {
			return base64_encode(openssl_encrypt($mensagem, 'aes-256-ctr', $this->chaveCompartilhadaAES, OPENSSL_RAW_DATA, $_SESSION['iv']));
		}
	}