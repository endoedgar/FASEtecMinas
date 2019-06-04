/* Developado por Edgar de Jesus Endo Junior */

function convertHexStringToByteArray(hexString) {
	result = [];

	for (var i = 0; i < hexString.length; i += 2) {
		result.push(parseInt(hexString.substr(i, 2),16));
	}
	
	return result;
}

function Uint8ToString(u8a){
  var CHUNK_SZ = 0x8000;
  var c = [];
  for (var i=0; i < u8a.length; i+=CHUNK_SZ) {
    c.push(String.fromCharCode.apply(null, u8a.subarray(i, i+CHUNK_SZ)));
  }
  return c.join("");
}

function getSearchParameters() {
      var prmstr = window.location.search.substr(1);
      return prmstr != null && prmstr != "" ? transformToAssocArray(prmstr) : {};
}

function transformToAssocArray( prmstr ) {
    var params = {};
    var prmarr = prmstr.split("&");
    for ( var i = 0; i < prmarr.length; i++) {
        var tmparr = prmarr[i].split("=");
        params[tmparr[0]] = tmparr[1];
    }
    return params;
}

function GestorDiffieHellman() {
	this.chaveLocal = null;
	this.ec = new EC('curve25519');
	this.chaveAES = null;
	this.resultiv = null;
	this.tentativasRestantes = 50;
	
	this.gerarNovaChavePrivadaLocal = function() {
		var chaveGerada = this.ec.genKeyPair();
		this.chaveLocal = chaveGerada;
		
		sessionStorage.chavePrivada = Buffer.from(this.chaveLocal.getPrivate('hex'), 'hex').toString('base64');
	}
	
	this.inicializarAES = function() {
		var aesCtr = new AESJS.ModeOfOperation.ctr(this.chaveAES, new AESJS.Counter(resultiv));
	}
	
	this.encriptografear = function(msg) {
		var textBytes = AESJS.utils.utf8.toBytes(msg);
		var aesCtr = new AESJS.ModeOfOperation.ctr(this.chaveAES, new AESJS.Counter(this.resultiv));
		var encryptedBytes = aesCtr.encrypt(textBytes);
		var encryptedHex = AESJS.utils.hex.fromBytes(encryptedBytes);
		var encryptedCipher = btoa(Uint8ToString(encryptedBytes));
		return encryptedCipher;
	}
	
	this.desencriptografear = function(msg) {
		var b = new Buffer(atob(msg), 'binary');
		var textBytes = AESJS.utils.hex.toBytes(b.toString('hex'));
		var aesCtr = new AESJS.ModeOfOperation.ctr(this.chaveAES, new AESJS.Counter(this.resultiv));
		var bytesdesencriptados = aesCtr.decrypt(textBytes);
		return Uint8ToString(bytesdesencriptados);
	}
	
	this.negociarChaves = function(chavePublica, iv) {
		var b = new Buffer(atob(iv), 'binary');
		var hexIv = b.toString('hex');
		var chavePublicaServidor = this.ec.keyFromPublic(atob(chavePublica).toString(16));

		if (sessionStorage.chavePrivada) {
			var b = new Buffer(atob(sessionStorage.chavePrivada), 'binary');
			this.chaveLocal = this.ec.keyFromPrivate(b.toString('hex'), 'hex');
		} else {
			this.gerarNovaChavePrivadaLocal();
		}

		var share = this.chaveLocal.derive(chavePublicaServidor.getPublic());

		var hexStringDiffieHellman = sha256(share.toString(16));
		this.chaveAES = convertHexStringToByteArray(hexStringDiffieHellman);

		this.resultiv = convertHexStringToByteArray(hexIv);
		
		console.log("Counter: " + hexIv);
		console.log('Diffie Hellman: ' + share.toString(16));
		console.log('Diffie Hellman SHA256 (CHAVE NEGOCIADA): ' + hexStringDiffieHellman);
	}
	
	this.enviarChavePublicaParaOServidor = function(getParams) {
		that = this;
		$.ajax({
			url: 'index.php',
			method: 'POST',
			data: {
				chavear: true,
				chavePublica: Buffer.from(this.chaveLocal.getPublic('hex'), 'hex').toString('base64'),
				mensagem: that.encriptografear('ALUNO_ETEC')
			}
		}).done(function(data) {
			var resultado = that.desencriptografear(data.cripto);
			if(resultado == 'SERVIDOR_ETEC') {
				document.getElementById('status').innerText = "OK";
				$.redirect('index.php', {'pntm': that.encriptografear(JSON.stringify(getParams))});
			}
		}).fail(function(data) {
			if(that.tentativasRestantes) {
				setTimeout(function() {
					that.enviarChavePublicaParaOServidor();
					document.getElementById('status').innerText = "Tentando novamente... (" + that.tentativasRestantes + ")";
					that.tentativasRestantes--;
				}, 1000);
			} else {
				document.getElementById('status').innerText = "Falha na troca de chaves Diffie-Hellman. Talvez seu navegador não possua suporte, ou o servidor esteja com problemas.";
			}
		});
	}
}