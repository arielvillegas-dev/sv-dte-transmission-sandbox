# SV DTE Transmission Sandbox (Facturación Electrónica El Salvador)

Demostración técnica independiente en **PHP 8.3 + Laravel 13** que implementa la arquitectura y reglas del flujo de **Documentos Tributarios Electrónicos (DTE)** de El Salvador con simuladores locales de firma JWS y transmisión al Ministerio de Hacienda.

> **Nota de confidencialidad:** Proyecto creado desde cero con datos 100 % sintéticos. **No** contiene certificados digitales reales (`.crt`), credenciales del MH, datos reales de contribuyentes ni código de sistemas en producción.

## 1. Problema que resuelve
Modela de forma limpia y reproducible las piezas más críticas de un sistema de transmisión DTE salvadoreño: generación atómica de correlativos y `numeroControl`, cálculo tributario diferenciado por tipo de documento (`01` Factura v2, `03` CCF v4, `14` Sujeto Excluido v2), validación estructural, firmado JWS simulado, manejo de **Contingencia v4** ante caídas de red y eventos de **Invalidación v3**.

## 2. Tecnologías utilizadas
- **Backend:** PHP `^8.3`, Laravel `^13`, Eloquent ORM, SQLite.
- **Pruebas:** PHPUnit `^12.5` (Unit + Feature sobre SQLite en memoria).

## 3. Arquitectura general
- `app/Services/Dte/DteCorrelativeService.php`: Generación transaccional con bloqueo (`lockForUpdate`) del formato `DTE-{tipoDte}-{estab}{pos}-{15_digitos}` por año fiscal.
- `app/Services/Dte/DteTaxCalculatorService.php`: Reglas tributarias de FE (`01`, IVA 13 % incluido), CCF (`03`, IVA 13 % agregado + retención 1 %) y FSE (`14`, retención renta 10 %).
- `app/Services/Dte/DteDocumentBuilderService.php` & `DteSchemaValidatorService.php`: Construcción y validación de estructura JSON (`identificacion`, `codigoGeneracion` UUID v4, versiones v2/v4).
- `app/Services/Dte/DteSignerSimulatorService.php`: Simulador JWS (`header.payload.signature`).
- `app/Services/Dte/MhTransmissionSimulatorService.php`: Flujo de transmisión normal (`PROCESADO` + `selloRecibido`), emisión diferida en contingencia, despacho de lote de contingencia v4 e invalidación v3.

## 4. Funcionalidades implementadas
- `POST /api/v1/dte/emit`: Emisión normal o en contingencia simulada (`01`, `03`, `14`).
- `POST /api/v1/dte/contingency/dispatch`: Agrupación y transmisión de lote de contingencia v4.
- `POST /api/v1/dte/{codigoGeneracion}/invalidate`: Anulación v3 con validación de estado.
- `GET /api/v1/dte/{codigoGeneracion}` y panel web en `/`.

## 5. Instrucciones para ejecutarlo

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

## 6. Pruebas disponibles
```bash
php artisan test
```
Verifica cálculos fiscales (`01`/`03`/`14`), correlativos secuenciales, validación estructural, emisión normal, modo contingencia + recuperación por lote v4 e invalidación v3.

## 7. Contribución real al desarrollo
Arquitectura y reglas de transmisión DTE diseñadas por Carlos Ariel Villegas Flores basadas en la especificación pública de Documentos Tributarios Electrónicos de El Salvador, con apoyo de herramientas de IA para generar el scaffolding Laravel y suite de pruebas automatizadas verificadas con `php artisan test`.
