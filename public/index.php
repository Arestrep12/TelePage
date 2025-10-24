<?php
declare(strict_types=1);

function get_server_ip(): string {
	$ip = $_SERVER['SERVER_ADDR'] ?? '';
	if (!$ip) {
		$hostname = gethostname();
		if ($hostname !== false) {
			$resolved = gethostbyname($hostname);
			if ($resolved !== $hostname) {
				$ip = $resolved;
			}
		}
	}
	return $ip ?: 'desconocida';
}

require_once __DIR__ . '/../templates/header.php';
?>
<section class="hero">
	<h1>Noticias de IA, sin ruido</h1>
	<p>Resumen semanal curado sobre IA, MLOps y producto.</p>
</section>
<section class="status">
	<p><strong>Backend:</strong> IP privada de esta instancia: <code><?php echo htmlspecialchars(get_server_ip(), ENT_QUOTES, 'UTF-8'); ?></code></p>
	<p>Si recargas, debería alternar entre instancias detrás del balanceador.</p>
</section>
<section>
	<a class="btn" href="/subscribe.php">Suscríbete ahora</a>
</section>
<?php require_once __DIR__ . '/../templates/footer.php';
