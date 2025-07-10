# Calculadora de Honorarios Municipales

Proyecto open-source para la **configuración y cálculo de derechos municipales** sobre metros cuadrados construidos. Permite definir, editar y respaldar la tabla de categorías y estados de obra, y calcular el monto según la normativa vigente de la Municipalidad de Posadas.

---

## 🧩 Características principales

* **Calculadora** dinámica de honorarios por m² y estado de obra.
* **Panel de administración** seguro:

  * Edición, alta y baja de categorías y UT.
  * Validación de superposiciones y huecos.
  * Backups automáticos (inicial) y manuales (por fecha).
  * Restauración de cualquier backup.
* Configuración persistente en **JSON** (sin base de datos).
* 100% **PHP nativo** + Bootstrap 5 (frontend responsive).
* Preparado para producción municipal y fácil de mantener.

---

## 📁 Estructura del proyecto

```
/
├── admin.php                   # Panel de configuración/abm categorías y backups
├── index.php                   # Calculadora principal
├── data/
│   ├── categorias.json
│   ├── estados_obra.json
│   ├── valor_ut.json
│   └── backups/
│       ├── categorias_backup_inicial.json
│       ├── categorias_backup_2024-07-03_22-19-00.json
│       └── ...
```

---

## 🛠️ Requisitos

* PHP 7.4+ (probado en 8.x)
* Web server Apache/Nginx o local (XAMPP/Laragon/Devilbox)
* No requiere base de datos

---

## 📝 Instalación y uso

Preferiblemente en la carpeta htdoc dentro de laragon (o Xampp)

```bash
git clone https://github.com/VillalbaLeandro/calculos-honorarios.git
cd calculos-honorarios


```

Luego, abrí en tu navegador:

* Calculadora: [http://localhost:8080/index.php](http://localhost:8080/index.php)
* Admin: [http://localhost:8080/admin.php](http://localhost:8080/admin.php)

---

## 🔒 Seguridad & backups

* Cada modificación crea un backup con fecha en `/data/backups/`.
* El primer acceso crea un backup inicial.
* Desde admin.php podés restaurar cualquier backup desde la lista disponible.

---

## 💡 Customización

* Tonalidad verde editable en el CSS.
* Agregá o eliminá categorías según la necesidad normativa.
* Podés adaptar el sistema a cualquier municipio simplemente cambiando los parámetros.

---

## 🤝 Créditos

Desarrollado por [Leandro Villalba](https://github.com/VillalbaLeandro) e Ignacio [Apellido]
