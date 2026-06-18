# Mundial 2026 - Gestión de Partidos

App web para gestionar partidos del Mundial 2026 con PHP + MySQL.

## Requisitos

- XAMPP (Apache + PHP 8+ + MySQL)
- Navegador web

## Instalación y ejecución

1. **Iniciar XAMPP**  
   Abrir XAMPP Control Panel y dar Start a **Apache** y **MySQL**.

2. **Importar base de datos**  
   Abrir phpMyAdmin (`http://localhost/phpmyadmin`) o usar la terminal:
   ```
   mysql -u root < database.sql
   ```

3. **Acceder a la app**  
   Abrir en el navegador:
   ```
   http://localhost/mundial-master/mundial-master/
   ```
   (ajustar la ruta según donde esté alojado el proyecto)

## Archivos principales

- `index.php` - Interfaz principal
- `conexion.php` - Conexión a MySQL
- `obtener_datos.php` - API JSON (grupos, partidos, estadísticas)
- `guardar_resultado.php` - Guardar/eliminar resultados
- `script.js` - Lógica del frontend
- `styles.css` - Estilos
- `database.sql` - Esquema y datos iniciales

## Uso

1. En **Fase de Grupos**, ingresar marcadores y guardar.
2. Al completar todos los grupos, se habilitan las **Eliminatorias**.
3. La sección **Estadísticas** muestra gráficos y totales.
