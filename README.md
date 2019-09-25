# encuestascdc
UAI Corporate Encuestas en Moodle

El módulo de encuestas de UAI Corporate permite exportar los resultados de una encuesta realizada con el módulo questionnaire de Moodle y aplicarle un CSS específico a elección.
Los css en particular utilizan primitivas de impresión, de tal forma que se pueda hacer Imprimir desde el browser y que se genere automáticamente un informe de calidad final para entregar a un cliente o para un Director de Programa.

Pre-requisitos
-----------
1. Moodle versión 3.5 o superior
2. Plugin questionnaire instalado de acuerdo a instrucciones en: https://moodle.org/plugins/mod_questionnaire

Instalación
-----------
Para instalar el plugin, clone desde github el repositorio de encuestascdc en el directorio local y luego ejecute un upgrade de la plataforma. Las siguientes instrucciones muestran paso a paso lo que debe ejecutar, asumiendo que moodle está instalado en /opt/moodle/. NOTA: La url del repositorio podría cambiar si es un fork del plugin.

1. cd /opt/moodle/local/
2. git clone https://github.com/webcursosuai/encuestascdc.git encuestascdc
3. sudo -u www-data /usr/bin/php /opt/moodle/admin/cli/upgrade.php

Pruebas
-----------
Requisitos:
1. Curso creado con uno o más profesores asignados y uno o más estudiantes matriculados.
2. Una encuesta de UAI Corporate, en el formato en que ellos utilizan. Escribir a andrea.pena@uai.cl para conseguir una de ser necesario. Disponibles en cdc.uai.cl
3. Al menos una respuesta completa en la encuesta. Se recomienda que al menos tres estudiantes contesten y que las respuestas abiertas incluyan vocales con tilde y eñes.

Emisión de informe
------------------
1. Navegar al curso
2. Dentro del menú del curso (siendo profesor o gestor) hacer clic en "Reporte encuesta".
3. Rellenar el formulario: Por ahora solo funciona "Reporte asignatura" (el global está en desarrollo). Seleccionar tipo de encuesta (probar con ambos). Plantilla de reporte utilizar "document" por ahora, el otro está en desarrollo (tipo ppt). Rellenar nombres de profesores (está en desarrollo que se rellenen solos cuando el curso tiene el profesor bien asignado) y coordinadora. En campos Empresa, Asignatura y Programa corregir los nombres de ser necesario.
4. Probar por separado encuesta para Profesor, Cliente y Director de Programa. En el caso de Cliente no deben aparecer nombres de profesores sino "Profesor 1", "Profesor 2" y así. En el caso del Director de Programa debe aparecer el nombre de cada profesor en un solo reporte. En el caso Profesor deben aparecer tantos informes como Profesores tenga el curso.

Respuesta rápida de encuestas
------------------------------
1. Navegar a /local/encuestascdc/login.php
2. Ingresar con nombre de usuario y clave.
3. Se deberán desplegar todas las encuestas que el usuario tenga en todos los cursos en que esté matriculado, con su estado (Contestada, Cerrada y Por contestar). Las primeras en aparecer deben ser las por contestar, el link de la misma debe llevar a contestar encuesta.

