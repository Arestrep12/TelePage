<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

$errors = [];
$success = null;

function sanitize_name(string $name): string {
	$name = trim($name);
	$name = strip_tags($name);
	$name = preg_replace('/[^\p{L}\p{N}\s\'\-\.,]/u', '', $name) ?? '';
	return $name;
}

function post(string $key): ?string {
	return isset($_POST[$key]) ? (string)$_POST[$key] : null;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
	$hp = post('hp');
	if ($hp !== null && $hp !== '') {
		$success = '¡Gracias por suscribirte!';
	} else {
		$name = sanitize_name(post('name') ?? '');
		$email = trim(post('email') ?? '');

		if ($name === '' || mb_strlen($name) < 1 || mb_strlen($name) > 120) {
			$errors[] = 'El nombre debe tener entre 1 y 120 caracteres.';
		}
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$errors[] = 'Email inválido.';
		}

		if (!$errors) {
			try {
				$stmt = $pdo->prepare('INSERT INTO subscribers (name, email) VALUES (:name, :email)');
				$stmt->execute([':name' => $name, ':email' => $email]);
				$success = '¡Listo! Te has suscrito correctamente.';
			} catch (PDOException $e) {
				$sqlState = $e->getCode();
				if ($sqlState === '23000') {
					$errors[] = 'Ese email ya está suscrito.';
				} else {
					if (defined('APP_DEBUG') && APP_DEBUG) {
						$errors[] = 'Error de base de datos: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
					} else {
						$errors[] = 'Ocurrió un error. Intenta más tarde.';
					}
				}
			}
		}
	}
}

require_once __DIR__ . '/../templates/header.php';
?>
<section>
	<h1>Suscríbete</h1>
	<p>Únete para recibir el mejor resumen de IA cada semana.</p>
	<?php if ($success): ?>
		<div class="alert success" role="status" tabindex="-1"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
	<?php endif; ?>
	<?php if ($errors): ?>
		<div class="alert error" role="alert" aria-live="assertive" tabindex="-1">
			<ul>
				<?php foreach ($errors as $err): ?>
					<li><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<form method="post" action="/subscribe.php" class="form" novalidate>
		<div class="field" hidden aria-hidden="true">
			<label for="hp">Deja este campo vacío</label>
			<input id="hp" name="hp" type="text" tabindex="-1" autocomplete="off">
		</div>
		<div class="field">
			<label for="name">Nombre</label>
			<input id="name" name="name" type="text" required minlength="1" maxlength="120" autocomplete="name" />
		</div>
		<div class="field">
			<label for="email">Email</label>
			<input id="email" name="email" type="email" required autocomplete="email" />
		</div>
		<button class="btn" type="submit">Suscribirme</button>
	</form>
</section>
<?php require_once __DIR__ . '/../templates/footer.php';
