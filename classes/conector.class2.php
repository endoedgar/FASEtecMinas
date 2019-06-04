<?php
	require 'config.php';
	class Conector {
		private $pdo;
		private $con_bd_logger;
		private $chaveHMAC;
		private $roteadores;
		private $NSA_servidor;
		private $NSA_bancoDeDados;
		private $NSA_usuario;
		private $NSA_senha;

		private $BDLocal_servidor;
		private $BDLocal_bancoDeDados;
		private $BDLocal_usuario;
		private $BDLocal_senha;

		public function __construct() {
			try {
				$this->chaveHMAC = Config::$chaveHMAC;
				$this->roteadores = Config::$roteadores;

				$this->NSA_servidor = Config::$NSA_Servidor['servidor'];
				$this->NSA_bancoDeDados = Config::$NSA_Servidor['bancoDeDados'];
				$this->NSA_usuario = Config::$NSA_Servidor['usuario'];
				$this->NSA_senha = Config::$NSA_Servidor['senha'];

				$this->BDLocal_servidor = Config::$BDLocal['servidor'];
				$this->BDLocal_bancoDeDados = Config::$BDLocal['bancoDeDados'];
				$this->BDLocal_usuario = Config::$BDLocal['usuario'];
				$this->BDLocal_senha = Config::$BDLocal['senha'];

				$host = $this->NSA_servidor;
				$db = $this->NSA_bancoDeDados;
				$usuario = $this->NSA_usuario;
				$senha = $this->NSA_senha;

				$this->pdo = new PDO("pgsql:host=$host;dbname=$db;user=$usuario;password=$senha");
				$this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

				$host = $this->BDLocal_servidor;
				$db = $this->BDLocal_bancoDeDados;
				$usuario = $this->BDLocal_usuario;
				$senha = $this->BDLocal_senha;

				$this->con_bd_logger = new PDO("mysql:dbname=$db;host=$host;charset=utf8", $usuario, $senha);

				$this->con_bd_logger->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
				$this->con_bd_logger->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			} catch(Exception $e) {
				throw new Exception('Houve uma falha na conexão com os banco de dados (NSA e/ou Interno). Tente novamente mais tarde.');
			}
		}
		
		public function inserirRegistro($usuario, $mac, $origem, $user_agent, $status) {
			$stmt = $this->con_bd_logger->prepare('INSERT INTO log_wifi_aluno VALUES(null, :usuario, NOW(), :mac, :origem, :user_agent, :status)');
			$stmt->execute(array(
				':usuario' => $usuario,
				':mac' => $mac,
				':origem' => $origem,
				':user_agent' => $user_agent,
				':status' => $status));
		}

		public function verificarLogin($usuario, $senha) {
			$stmt = $this->pdo->prepare('SELECT cadaluno.idaluno, cadaluno.rm, cadaluno.nome, cadaluno.datanasc, tbfotoaluno.foto FROM cadaluno LEFT JOIN tbfotoaluno ON cadaluno.rm = tbfotoaluno.rm WHERE cadaluno.rm=:rm AND (senhan=md5(cadaluno.rm  || md5(:senha)));');
			$stmt->execute(array(
				'rm' => $usuario,
				'senha' => $senha));
			return $stmt->fetch(PDO::FETCH_ASSOC);
		}
		
		public function listarUltimasTentativasDeLogin($quantas, $maisNovosQue) {
			$preparedVetor = array(':quantas' => $quantas);
			$sql = 'SELECT * FROM log_wifi_aluno ORDER BY id DESC LIMIT :quantas';
			if(!is_null($maisNovosQue)) {
				$sql = 'SELECT * FROM log_wifi_aluno WHERE id > :maisNovosQue ORDER BY id ASC LIMIT :quantas';
				$preparedVetor[':maisNovosQue'] = $maisNovosQue;
			}
			$stmt = $this->con_bd_logger->prepare($sql);
			$stmt->execute($preparedVetor);
			
			$listaRetorno = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
			$cache = array();
			foreach($listaRetorno as &$elemento) {
				if(isset($cache[$elemento['usuario']])) {
					$elemento['dadosNSA'] = $cache[$elemento['usuario']];
				} else {
					$stmt2 = $this->pdo->prepare('SELECT cadaluno.idaluno, cadaluno.rm, cadaluno.nome, cadaluno.datanasc, tbfotoaluno.foto FROM cadaluno LEFT JOIN tbfotoaluno ON cadaluno.rm = tbfotoaluno.rm WHERE cadaluno.rm=:rm;');
					$stmt2->execute(array('rm' => $elemento['usuario']));
					$obj = $stmt2->fetch(PDO::FETCH_ASSOC);
					if($obj != false) {
						if(gettype($obj['foto']) == 'resource') {
							$obj['foto'] = base64_encode(stream_get_contents($obj['foto']));
						} else {
							$obj['foto'] = '';
						}
						$cache[$elemento['usuario']] = $obj;
						$elemento['dadosNSA'] = $cache[$elemento['usuario']];
					}
				}
				
				$stmt2 = $this->pdo->prepare('SELECT * FROM relaluno JOIN cadcurso ON relaluno.idcurso = cadcurso.idcurso WHERE rm=:rm;');
				$stmt2->execute(array('rm' => $elemento['usuario']));
				$obj = $stmt2->fetchAll(PDO::FETCH_ASSOC);
					
				$elemento['dadosTurma'] = $obj;
			}
			
			
			return $listaRetorno;
		}
		
		public function inserirLogSSH($origem, $cmd, $retorno) {
			$stmt = $this->con_bd_logger->prepare('INSERT INTO log_ssh (origem, cmd, retorno) VALUES(:origem, :cmd, :retorno)');
			$stmt->execute(array(
				':origem' => $origem,
				':cmd' => $cmd,
				':retorno' => $retorno,
			));
		}
		
		public function executarComandoRoteador($servidor, $cmd) {
			$cr = curl_init();
			$agente='curl agente';
			$vet = array(
				'cmd' => $cmd,
				'salt' => base64_encode(random_bytes(16)),
				'server' => $servidor
			);
			$vet['hmac'] = base64_encode(hash_hmac('sha256', $agente.http_build_query($vet), $this->chaveHMAC, true));
			curl_setopt($cr, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($cr, CURLOPT_URL, 'http://10.66.161.11:8008/?'.http_build_query($vet));
			curl_setopt($cr, CURLOPT_USERAGENT, $agente);
			$retorno = curl_exec($cr);
			curl_close($cr);
			
			$this->inserirLogSSH($servidor, $cmd, $retorno);
			
			if(strlen($retorno) > 0) {
				$retorno = json_decode($retorno, true);
				$ultimo_erro = json_last_error();
				if($ultimo_erro == 0) {
					if(isset($retorno['sucesso'])) {
						return $retorno;
					} else
						return array('sucesso' => false, 'mensagem' => 'Retorno sem retornar sucesso, WTF?'); 
				} else
					return array('sucesso' => false, 'mensagem' => 'Erro ao interpretar JSON. (código '.$ultimo_erro.')');
			} else
				return array('sucesso' => false, 'mensagem' => 'Resposta vazia recebida do Servidor Python.');
		}
		
		public function randomizarRedeWifiAlunos() {
			$novaSenha = bin2hex(random_bytes(4));
			$novoSsid = 'etecw - senha: ' . $novaSenha;
			foreach($this->roteadores as $roteadorNome => $roteador) {
				if(is_array($roteador['wifi-iface'])) {
					foreach($roteador['wifi-iface'] as $wiface) {
						$this->executarComandoRoteador($roteadorNome, 'uci set wireless.@wifi-iface['.$wiface.'].ssid=\''.$novoSsid.'\'');
						$this->executarComandoRoteador($roteadorNome, 'uci set wireless.@wifi-iface['.$wiface.'].key=\''.$novaSenha.'\'');
					}
				} else {
					$this->executarComandoRoteador($roteadorNome, 'uci set wireless.@wifi-iface['.$roteador['wifi-iface'].'].ssid=\''.$novoSsid.'\'');
					$this->executarComandoRoteador($roteadorNome, 'uci set wireless.@wifi-iface['.$roteador['wifi-iface'].'].key=\''.$novaSenha.'\'');
				}
				$this->executarComandoRoteador($roteadorNome, 'uci commit');
				$this->executarComandoRoteador($roteadorNome, 'reboot');
			}
			$stmt = $this->con_bd_logger->prepare('TRUNCATE sessoes_wifi_aluno;');
			$stmt->execute(array());
		}
		
		public function removerSessao($item) {
			$this->executarComandoRoteador($item['origem'], 'ndsctl deauth '.$item['token']);
			/*$this->executarComandoRoteador($item['origem'], '/etc/init.d/dnsmasq stop');
			$this->executarComandoRoteador($item['origem'], 'sed -i \'/'.$item['mac'].'/d\' /tmp/dhcp.leases');
			$this->executarComandoRoteador($item['origem'], '/etc/init.d/dnsmasq start');*/
				
			$retorno = $this->executarComandoRoteador($item['origem'], 'hostapd_cli interface | grep ^wlan');
			if($retorno['sucesso']) {
				$interfaces = explode("\n", $retorno['retorno']);
				
				foreach($interfaces as $interface) {
					if(strlen(trim($interface)) > 0)
						$this->executarComandoRoteador($item['origem'], 'hostapd_cli -i '.$interface.' disassociate '.$item['mac']);
				}
			}
			
			/*$roteador = $this->roteadores[$item['origem']];
			
			if(is_array($roteador['wifi-iface'])) {
				foreach($roteador['wifi-iface'] as $wiface) {
					$this->executarComandoRoteador($item['origem'], 'hostapd_cli -i '.$wiface.' disassociate '.$item['mac']);
				}
			} else {
				$this->executarComandoRoteador($item['origem'], 'hostapd_cli -i '.$wiface.' disassociate '.$item['mac']);
			}*/
				
			$stmt2 = $this->con_bd_logger->prepare('DELETE FROM sessoes_wifi_aluno WHERE id = :id');
			$stmt2->execute(
				array(
					':id' => $item['id']
				)
			);
		}
		
		public function kickarQualquerSecaoQueNaoSeja($usuario, $gatewayname, $dadosCliente) {
			$stmt = $this->con_bd_logger->prepare('SELECT id, token, mac, origem FROM sessoes_wifi_aluno WHERE usuario = :usuario AND token <> :token AND mac <> :mac');
			$stmt->execute(
				array(
					':usuario' => $usuario,
					':token' => $dadosCliente['token'],
					':mac' => $dadosCliente['mac']
				)
			);
			
			$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
			$n = 0;
			foreach($lista as $item) {
				$this->removerSessao($item);
				
				if($dadosCliente['mac'] != $item['mac']) {
					$n++;
				}
			}
			
			return $n;
		}
		
		public function autoriza($gatewayname, $clientip, $usuario, $motivo) {
			$ret = $this->executarComandoRoteador($gatewayname, 'ndsctl json '.$clientip);
			$ret = $this->verificarRetornoRoteador($ret);
			
			$mac = '';
			$retorno = array(
				'sucesso' => false
			);
			
			if($ret['sucesso'] == 1) {
				$ret = json_decode($ret['retorno'], true);
							
				$ultimo_erro = json_last_error();
				if($ultimo_erro == 0) {
					if(isset($ret['id'])) {
						$dadosCliente = $ret;
						$mac = $dadosCliente['mac'];
						
						$msg = $motivo;
						$this->executarComandoRoteador($gatewayname, 'ndsctl auth '.$dadosCliente['token']);
						$retorno = array('sucesso' => true, 'mensagem' => $msg, 'dadosClienteNDS' => $dadosCliente);
					} else
						$retorno['mensagem'] =  'IP do cliente não encontrado. Tente novamente.';
				} else
					$retorno['mensagem'] = 'Erro ao interpretar JSON. (código '.$ultimo_erro.')';
			} else
				$retorno = $ret;
			
			$this->inserirRegistro($usuario, $mac, $gatewayname, $retorno['mensagem']);
			
			return $retorno;
		}
		
		public function registrarEstaSessao($usuario, $gatewayname, $dadosCliente) {
			$this->executarComandoRoteador($gatewayname, 'ndsctl auth '.$dadosCliente['token']);
			
			$stmt = $this->con_bd_logger->prepare('INSERT INTO sessoes_wifi_aluno VALUES(null, :usuario, :origem, :ip, :mac, FROM_UNIXTIME(:active), :token, :state)');
			$stmt->execute(
				array(
					':usuario' => $usuario,
					':origem' => $gatewayname,
					':ip' => $dadosCliente['ip'],
					':mac' => $dadosCliente['mac'],
					':active' => $dadosCliente['active'],
					':token' => $dadosCliente['token'],
					':state' => $dadosCliente['state']
				)
			);
		}
		
		private function verificarRetornoRoteador($ret) {
			$retorno = $ret;
			if($retorno['sucesso'] == 1) {
				$retorno['sucesso'] = 0;
				if(isset($retorno['retorno'])) {
					if(isset($retorno['seuHost']))
						$retorno['sucesso'] = 1;
					else
						$retorno['mensagem'] = 'Sem HOST de retorno.';
				} else
					$retorno['mensagem'] = 'Resposta JSON sem retorno.';
			}
			return $retorno;
		}
		
/*		public verificarSeUsuarioJaLogou($gatewayname, $user_agent, $clientip) {
			$ret = $this->executarComandoRoteador($gatewayname, 'ndsctl json '.$clientip);
			$ret = $this->verificarRetornoRoteador($ret);
			
			$mac = '';
			
			$retorno = array(
				'sucesso' => false
			);
			
			if($ret['sucesso'] == 1) {
				$hostRetorno = $ret['seuHost'];
				$ret = json_decode($ret['retorno'], true);
				
				$ultimo_erro = json_last_error();
				if($ultimo_erro == 0) {
					if(isset($ret['id'])) {
						$dadosCliente = $ret;
						$mac = $dadosCliente['mac'];
						
						$obterDadosDoUltimoAlunoUsandoEsseMac = $this->existeSessaoComEsteMac($mac);
						if() {
						}
						
						if($dadosCliente['state'] != 'Authenticated') {
							$this->registrarEstaSessao($usuario, $gatewayname, $dadosCliente);
							$n = $this->kickarQualquerSecaoQueNaoSeja($usuario, $gatewayname, $dadosCliente);
							
							$msg = 'Olá ' . $login['nome'];
							
							if($n > 0) {
								if($n == 1) {
									$msg .= ' ('.$n.' sessão com dispositivo diferente foi terminada)';
								} else {
									$msg .= ' ('.$n.' sessões com dispositivos diferentes foram terminadas)';
								}
							}
							
							$retorno = array('sucesso' => true, 'mensagem' => $msg, 'dadosClienteNDS' => $dadosCliente, 'dadosLogin' => $login, 'hostRetorno' => $hostRetorno);
						} else {
							$ret = $this->executarComandoRoteador($gatewayname, 'ndsctl json');
							$ret = $this->verificarRetornoRoteador($ret);
							if($ret['sucesso'] == 1) {
								$ret = json_decode($ret['retorno'], true);
								
								$ultimo_erro = json_last_error();
								if($ultimo_erro == 0) {
									if(isset($ret['clients'])) {
										$ipsIguais=0;
										foreach($ret['clients'] as $client) {
											if($client['ip'] == $dadosCliente['ip']) {
												$ipsIguais++;
											}
										}
										if($ipsIguais > 0) {
											if($ipsIguais == 1)
												$retorno = array('sucesso' => true, 'mensagem' => 'Você já está autenticado(a) '.$login['nome'], 'dadosClienteNDS' => $dadosCliente, 'dadosLogin' => $login, 'hostRetorno' => $hostRetorno);
											else {
												$retorno['mensagem'] = 'Há um conflito de '.$ipsIguais.' IPs do seu dispositivo neste roteador.. Este é um BUG conhecido que será corrigido nos próximos dias. Tente novamente mais tarde.';
											}
										} else
											$retorno['mensagem'] = 'Roteador não retornou seu dispositivo como autenticado, favor tentar novamente.';
									} else
										$retorno['mensagem'] = 'Roteador não retornou uma lista de clientes.';
								} else
									$retorno['mensagem'] = 'Erro ao interpretar JSON. (código '.$ultimo_erro.')';
							} else
								$retorno = $ret;
							
						}
					} else
						$retorno['mensagem'] =  'IP do cliente não encontrado. Tente novamente.';
				} else
					$retorno['mensagem'] = 'Erro ao interpretar JSON. (código '.$ultimo_erro.')';
			} else
				$retorno = $ret;
			
			$this->inserirRegistro($usuario, $mac, $gatewayname, $user_agent, $retorno['mensagem']);
			
			return $retorno;
		}*/
		
		public function login($usuario, $senha, $gatewayname, $user_agent, $clientip) {
			$login = $this->verificarLogin($usuario, $senha);
			$mac = '';
			
			$retorno = array(
				'sucesso' => false
			);
			
			if($login != false) {
				$ret = $this->executarComandoRoteador($gatewayname, 'ndsctl json '.$clientip);
				$ret = $this->verificarRetornoRoteador($ret);
				if($ret['sucesso'] == 1) {
							$hostRetorno = $ret['seuHost'];
							$ret = json_decode($ret['retorno'], true);
							
							$ultimo_erro = json_last_error();
							if($ultimo_erro == 0) {
								if(isset($ret['id'])) {
									$dadosCliente = $ret;
									$mac = $dadosCliente['mac'];
									
									if($dadosCliente['state'] != 'Authenticated') {
										$this->registrarEstaSessao($usuario, $gatewayname, $dadosCliente);
										$n = $this->kickarQualquerSecaoQueNaoSeja($usuario, $gatewayname, $dadosCliente);
										
										$msg = 'Olá ' . $login['nome'];
										
										if($n > 0) {
											if($n == 1) {
												$msg .= ' ('.$n.' sessão com dispositivo diferente foi terminada)';
											} else {
												$msg .= ' ('.$n.' sessões com dispositivos diferentes foram terminadas)';
											}
										}
										
										$retorno = array('sucesso' => true, 'mensagem' => $msg, 'dadosClienteNDS' => $dadosCliente, 'dadosLogin' => $login, 'hostRetorno' => $hostRetorno);
									} else {
										$ret = $this->executarComandoRoteador($gatewayname, 'ndsctl json');
										$ret = $this->verificarRetornoRoteador($ret);
										if($ret['sucesso'] == 1) {
											$ret = json_decode($ret['retorno'], true);
											
											$ultimo_erro = json_last_error();
											if($ultimo_erro == 0) {
												if(isset($ret['clients'])) {
													$ipsIguais=0;
													foreach($ret['clients'] as $client) {
														if($client['ip'] == $dadosCliente['ip']) {
															$ipsIguais++;
														}
													}
													if($ipsIguais > 0) {
														if($ipsIguais == 1)
															$retorno = array('sucesso' => true, 'mensagem' => 'Você já está autenticado(a) '.$login['nome'], 'dadosClienteNDS' => $dadosCliente, 'dadosLogin' => $login, 'hostRetorno' => $hostRetorno);
														else {
															$retorno['mensagem'] = 'Há um conflito de '.$ipsIguais.' IPs do seu dispositivo neste roteador.. Este é um BUG conhecido que será corrigido nos próximos dias. Tente novamente mais tarde.';
														}
													} else
														$retorno['mensagem'] = 'Roteador não retornou seu dispositivo como autenticado, favor tentar novamente.';
												} else
													$retorno['mensagem'] = 'Roteador não retornou uma lista de clientes.';
											} else
												$retorno['mensagem'] = 'Erro ao interpretar JSON. (código '.$ultimo_erro.')';
										} else
											$retorno = $ret;
										
									}
								} else
									$retorno['mensagem'] =  'IP do cliente não encontrado. Tente novamente.';
							} else
								$retorno['mensagem'] = 'Erro ao interpretar JSON. (código '.$ultimo_erro.')';
				} else
					$retorno = $ret;
						
			} else {
				$retorno['mensagem'] = 'Usuário ou senha inválidos';
			}
			
			$this->inserirRegistro($usuario, $mac, $gatewayname, $user_agent, $retorno['mensagem']);
			
			return $retorno;
		}
	};
	
	if(isset($_GET['r'])) {
		$con = new Conector();
		$con->randomizarRedeWifiAlunos();
	}
?>
