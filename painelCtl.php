<?php
	require_once 'classes/conector.class2.php';
	
	class WebserviceWifiEtec {
		private $con;
		
		public function __construct() {
			$this->con = new Conector();
		}
		
		public function listarAlunos($quantos, $maisNovosQue) {
			return json_encode($this->con->listarUltimasTentativasDeLogin($quantos, $maisNovosQue));
		}
	}
	
	$c = new WebserviceWifiEtec();
	if(isset($_GET['maisNovosQue'])) {
		if($_GET['maisNovosQue'] != 'null')
			$maisNovosQue = $_GET['maisNovosQue'];
		else
			$maisNovosQue = null;
	} else
		$maisNovosQue = null;
	header('Content-Type: application/json');
	echo ($c->listarAlunos(500, $maisNovosQue));
?>