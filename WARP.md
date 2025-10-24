# Proyecto: Newsletter IA — Sitio PHP básico para EC2 (MVP)

## Resumen
Sitio web mínimo en PHP (LAMP) para una newsletter de noticias de IA. Consta de 2 páginas:
- Inicio (`/index.php`): landing informativa con resumen de la newsletter y enlaces
- Suscríbete (`/subscribe.php`): formulario que inserta nombre y email en MySQL

Este MVP forma parte de una arquitectura mayor en AWS compuesta por 6 instancias EC2:
- Públicas (3): `ns1` y `ns2` (DNS autoritativos con BIND), `lb` (Apache como balanceador HTTP)
- Privadas (3): `app1` y `app2` (esta app PHP idéntica, detrás del LB), `db` (MySQL 8)

Objetivo: el LB alterna peticiones (round-robin) entre `app1` y `app2`; la BD sólo es accesible desde `app1/app2`; los DNS resuelven el dominio del sitio hacia el LB.

## Alcance del MVP
- UI básica, sin frameworks (HTML + CSS simple)
- Dos páginas:
  - Landing (`/index.php`): muestra IP privada de la instancia (para validar el balanceador) y contenido informativo
  - Suscripción (`/subscribe.php`): formulario con nombre y email para unirse al newsletter
- Validación en servidor (PHP) y cliente (HTML5)
- Inserción en DB con consultas preparadas (PDO)
- Mensajes de éxito/errores visibles y accesibles

## Stack y supuestos
- PHP 8.1+ (ideal 8.2)
- Apache (httpd) o Nginx + PHP-FPM (simple: Apache)
- MySQL 8.x (recomendado en RDS; opcional en la misma EC2 para pruebas)
- Sistema: Amazon Linux 2023 o Ubuntu 22.04

## Arquitectura y estructura de archivos
```
Entrega2_Telematica/
├─ public/
│  ├─ index.php           # Landing con resumen + IP privada de la instancia en pantalla
│  ├─ subscribe.php       # Form GET/POST, inserta nombre+email en MySQL y muestra estados
│  └─ assets/
│     └─ css/
│        └─ styles.css    # Estilos mínimos (mobile-first)
├─ includes/
│  ├─ config.php          # Lee env y expone constantes/vars de conexión
│  └─ db.php              # Crea $pdo (PDO) con atributos seguros
├─ templates/
│  ├─ header.php          # <head> + navbar mínima
│  └─ footer.php          # footer + scripts mínimos
└─ WARP.md                # (este documento)
```

## Base de datos (MySQL)
Tabla mínima para suscriptores. Opcional: tabla de posts en futuro.

```sql
-- Crear base de datos (si no existe)
CREATE DATABASE IF NOT EXISTS newsletter_ai
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE newsletter_ai;

-- Tabla de suscriptores
CREATE TABLE IF NOT EXISTS subscribers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(255) NOT NULL,
  consent TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Configuración (variables de entorno sugeridas)
- `DB_HOST` (p.ej. `localhost` o endpoint de RDS)
- `DB_NAME` (p.ej. `newsletter_ai`)
- `DB_USER`
- `DB_PASS`

`includes/config.php` leerá estas variables (o valores por defecto) y `includes/db.php` construirá un `PDO` con DSN `mysql:host=...;dbname=...;charset=utf8mb4` y atributos:
- `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`
- `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`
- `PDO::ATTR_EMULATE_PREPARES => false`

## Flujo y validaciones
- `GET /subscribe.php`: muestra formulario (campos `name` y `email`; honeypot opcional)
- `POST /subscribe.php`:
  - Validar `email` con `filter_var($email, FILTER_VALIDATE_EMAIL)`
  - Sanitizar `name` (`trim`, longitud 1–120, caracteres seguros)
  - Insert con consulta preparada `INSERT INTO subscribers (name, email) VALUES (:name, :email)`
  - Manejo de duplicados (capturar `SQLSTATE[23000]` por `email` único)
  - Redirección o render de mensaje de éxito/errores
- `GET /index.php` (landing): mostrar IP privada de la instancia para verificar el LB, p.ej. `$_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname())`

Accesibilidad básica: labels asociados, mensajes con roles ARIA, foco en feedback.

## Arquitectura AWS (6 EC2) y despliegue

Diagrama lógico (simplificado):
```
Internet
  |                 |-------------- VPC (privada) --------------|
 [ns1]  [ns2]   [lb] ----->  [app1]   [app2]      [db]
  DNS    DNS     Apache       PHP      PHP        MySQL
  pub    pub     pub          priv     priv       priv
```

1) Red (VPC y subredes)
- VPC /16 con al menos 2 subredes: pública (/24) y privada (/24). Ideal multi-AZ.
- Internet Gateway (IGW) asociado a la VPC; ruta 0.0.0.0/0 a IGW en la subred pública.
- NAT Gateway en la pública; ruta 0.0.0.0/0 a NAT en la subred privada (recomendado).
- Asignar Elastic IP al balanceador `lb`. Los DNS `ns1/ns2` también con EIP.

2) Security Groups (SG)
- sg-dns-public: IN UDP/TCP 53 desde 0.0.0.0/0; IN TCP 22 sólo desde IP admin; OUT todo.
- sg-lb-public: IN 80/443 desde 0.0.0.0/0; IN 22 restringido; OUT a sg-app-private:80.
- sg-app-private: IN 80 desde sg-lb-public; IN 22 desde bastion/VPN; OUT a sg-db-private:3306.
- sg-db-private: IN 3306 desde sg-app-private; OUT mínimo necesario.

3) Instancias (Amazon Linux 2023, t2.micro para demo)
- Públicas: `ns1`, `ns2` (BIND9), `lb` (Apache HTTPD + mod_proxy_balancer).
- Privadas: `app1`, `app2` (LAMP con esta app PHP), `db` (MySQL 8.x).

4) DNS autoritativo con BIND9 (ns1/ns2)
- Paquetes: `sudo dnf install -y bind bind-utils`
- Abrir 53/UDP y 53/TCP en sg-dns-public. Habilitar servicio: `sudo systemctl enable --now named`

En `ns1` (master) — /etc/named.conf (fragmento):
```conf
options { directory "/var/named"; listen-on port 53 { any; }; allow-query { any; }; recursion no; dnssec-validation no; };
zone "tu-dominio.com" IN {
  type master;
  file "/var/named/tu-dominio.com.zone";
  allow-transfer { <IP_PUBLICA_NS2>; };
  also-notify { <IP_PUBLICA_NS2>; };
};
```

Zona en `ns1` — /var/named/tu-dominio.com.zone:
```dns
$TTL 300
@   IN SOA ns1.tu-dominio.com. admin.tu-dominio.com. (
        2025102401 3600 600 1209600 300 )
    IN NS  ns1.tu-dominio.com.
    IN NS  ns2.tu-dominio.com.
@   IN A   <IP_PUBLICA_LB>
www IN CNAME @
ns1 IN A   <IP_PUBLICA_NS1>
ns2 IN A   <IP_PUBLICA_NS2>
lb  IN A   <IP_PUBLICA_LB>
```

En `ns2` (secondary) — /etc/named.conf (fragmento):
```conf
options { directory "/var/named"; listen-on port 53 { any; }; allow-query { any; }; recursion no; dnssec-validation no; };
zone "tu-dominio.com" IN {
  type secondary;
  file "/var/named/slaves/tu-dominio.com.zone";
  masters { <IP_PUBLICA_NS1>; };
};
```

Validar y reiniciar:
- `sudo named-checkconf && sudo named-checkzone tu-dominio.com /var/named/tu-dominio.com.zone`
- `sudo systemctl restart named`
- En el registrador: apuntar NS a ns1/ns2 y crear A/glue records.

5) Balanceador HTTP con Apache (lb)
- Paquetes: `sudo dnf install -y httpd mod_ssl`
- Asegura módulos: proxy, proxy_http, proxy_balancer, lbmethod_byrequests, headers.
- Config `/etc/httpd/conf.d/lb.conf`:
```apache
<VirtualHost *:80>
  ServerName tu-dominio.com
  ProxyPreserveHost On
  <Proxy "balancer://app_pool">
    BalancerMember "http://10.0.2.10:80" route=app1
    BalancerMember "http://10.0.2.11:80" route=app2
    ProxySet lbmethod=byrequests
  </Proxy>
  ProxyPass "/" "balancer://app_pool/"
  ProxyPassReverse "/" "balancer://app_pool/"
</VirtualHost>
```
- Habilitar: `sudo systemctl enable --now httpd`
- TLS (recomendado): `sudo dnf install -y certbot python3-certbot-apache && sudo certbot --apache -d tu-dominio.com`

6) App PHP (app1/app2, privadas)
- `sudo dnf update -y && sudo dnf install -y httpd php php-mysqlnd php-pdo`
- `sudo systemctl enable --now httpd`
- Desplegar código: `public/` a `/var/www/html` y `includes/`, `templates/` a `/var/www/` (ajusta `require` si es necesario).
- Variables de entorno en systemd/httpd o usar `includes/config.php` (DB_HOST = IP privada de `db`).

7) Base de datos MySQL (db, privada)
- Instalar MySQL 8.x (o MariaDB para demo). Abrir 3306 sólo a sg-app-private.
- Crear DB y usuario mínimos:
```sql
CREATE DATABASE IF NOT EXISTS newsletter_ai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'newsletter'@'10.0.2.%' IDENTIFIED BY 'REPLACE_ME_STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON newsletter_ai.* TO 'newsletter'@'10.0.2.%';
FLUSH PRIVILEGES;
```
- Ajustar `bind-address` para escuchar en la privada si aplica. Endurecer `mysql_secure_installation`.

8) Pruebas y validación
- DNS: `dig NS tu-dominio.com @<IP_NS1>` y `dig A tu-dominio.com @<IP_NS2>` debe devolver el A del LB.
- HTTP: `curl -I http://tu-dominio.com/` debe responder 200 desde el LB.
- Landing: ver la IP privada del backend en pantalla (p.ej. 10.0.2.10); tras recargar, debe alternar a la IP de la otra instancia por round-robin.
- Round-robin: emitir múltiples curls y observar logs en `app1/app2` (`/var/log/httpd/access_log`) alternando tráfico. Opcional: mantener la IP visible en `index.php` para ver el backend.

## Criterios de aceptación
- DNS autoritativo operativo: `NS` en registrador apuntando a `ns1/ns2`; resolución A hacia el LB.
- LB alterna peticiones entre `app1` y `app2` (round-robin) comprobable en logs o con marcador temporal en `index.php`.
- Landing muestra la IP privada de la instancia backend en pantalla.
- `db` sólo accesible desde `app1/app2` (SG), sin exposición pública; `app` sin acceso entrante desde Internet.
- GET `/` carga en <200ms desde el LB (sin TLS) y HTML <30KB; POST `/subscribe.php` inserta nombre+email válidos y maneja duplicados.
- Sin warnings PHP en logs (`/var/log/httpd/error_log`) ni errores en `named`/`httpd`.

## Pendiente por definir (tu input)
- Copy y branding (nombre de la newsletter, paleta de colores)
- Idioma(s) y formato de fecha
- Política de privacidad/consentimiento
- Destino de los leads (solo DB o export/CSV/integ. futuro)
- ¿RDS vs MySQL local? Región y clase
- Dominio/DNS

## Roadmap (futuro opcional)
- Página “Gracias” con recomendaciones de posts
- Página Admin protegida para listar/descargar suscriptores
- Tabla `posts` y listado en `index.php` (RSC no aplica, es PHP clásico)
- Captcha liviano o honeypot
- CI/CD (GitHub Actions + rsync/SSH)
