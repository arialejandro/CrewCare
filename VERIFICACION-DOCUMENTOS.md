# Cómo verificar un documento de CrewCare

*Guía para terceros — abogados, auditores, aseguradoras. No hace falta tener cuenta en CrewCare ni conocimientos técnicos. Los comandos del final los puede correr cualquier persona con una computadora.*

---

## Antes que nada: las dos preguntas

Un documento de CrewCare (un reporte de accidente, un acta de inspección, un contrato firmado, un reporte de wrap…) trae **dos garantías distintas**, y conviene no confundirlas porque **responden preguntas diferentes y las contesta gente diferente**:

| | La pregunta | Quién la contesta | Dónde se comprueba |
|---|---|---|---|
| **El sello** | *¿Este documento fue alterado?* | CrewCare | En el **verificador público** de CrewCare, escaneando el QR |
| **El timbre** | *¿Este documento ya existía en tal fecha?* | Un tercero neutral (**freeTSA**), no CrewCare | Con **OpenSSL**, **sin CrewCare** |

La segunda es la importante para no depender de nosotros: el timbre lo pone un **tercero independiente**, y usted lo verifica **por su cuenta**. Si mañana CrewCare desapareciera, el timbre seguiría siendo comprobable.

---

## Una analogía, para aterrizar dos palabras

Todo esto descansa en una idea: la **huella digital** de un documento (su *hash*).

Imagine una máquina que lee un documento entero y escupe una cadena de 64 caracteres —una especie de **huella dactilar del texto**. Tiene tres propiedades que la hacen útil:

1. **El mismo documento siempre da la misma huella.**
2. **Cambiar lo más mínimo —una coma, un acento, un dígito— cambia la huella por completo.** No "un poquito": entera.
3. **De la huella no se puede reconstruir el documento.** Por eso la huella se puede mostrar en público sin revelar nada del contenido.

Con esa sola idea se explican las dos garantías:

- **El sello** es CrewCare guardando la huella del documento *en el momento de emitirlo*, cerrada con una llave secreta. Para verificar, el verificador vuelve a calcular la huella del documento que usted tiene y la compara con la sellada. **¿Coinciden? No se alteró. ¿No coinciden? Se alteró.**
- **El timbre** es un tercero neutral (freeTSA) que recibió esa huella en un instante y firmó, con su propia firma, *"vi esta huella tal día a tal hora"*. Como la firma es **de ellos, no de CrewCare**, usted la verifica con el certificado público de ellos —sin pasar por nosotros. Y **nadie puede conseguir un timbre con fecha del pasado**: la fecha la pone freeTSA cuando recibe la huella.

---

## Parte A — Verificar el SELLO (¿fue alterado?)

Cada documento sellado lleva impreso un **código QR** y una URL de la forma:

```
https://crewcarer.mx/verificar/<tipo>/<uuid>
```

**Pasos:**

1. Escanee el QR con el teléfono, o escriba la URL en cualquier navegador. **No pide contraseña**: es una página pública.
2. La página vuelve a calcular la huella del documento y la compara con la que se selló. Le muestra uno de estos estados:

| Estado en pantalla | Qué significa |
|---|---|
| ✅ **Documento íntegro y vigente** | El contenido **no cambió** desde que se emitió, y sigue vigente. |
| ⚠️ **Íntegro, pero no vigente** | El sello es auténtico y el contenido no cambió, **pero** el documento fue retirado/cerrado/sustituido (aparece la fecha). Retirar **no es** alterar. |
| ⛔ **Documento alterado** | El contenido **no coincide** con el sello. Alguien lo modificó después de emitirlo. |
| **Documento sin sello** | El documento existe pero nunca se selló (es anterior al sellado). No hay integridad que comprobar. |

3. La página **nunca muestra el contenido** del documento (ni nombres, ni diagnósticos, ni ubicaciones): solo dice si fue alterado o no. Eso es a propósito.

> **Ayuda visual:** junto al folio, el verificador dibuja un pequeño "identicon" derivado de la huella. No aporta seguridad extra; sirve para que dos documentos distintos se distingan de un vistazo, incluso impresos.

---

## Parte B — Verificar el TIMBRE **sin CrewCare** (¿existía en tal fecha?)

Aquí está el punto: esto **no depende de que usted confíe en CrewCare**. El timbre lo emitió **freeTSA** (una Autoridad de Sellado de Tiempo independiente, `freetsa.org`) bajo el estándar internacional **RFC 3161**, y usted lo comprueba con herramientas públicas.

Necesita **tres piezas**, todas obtenibles sin cuenta:

1. **El token del timbre** (`.tsr`) — se descarga desde el verificador con el botón **"Descargar timbre (.tsr)"**.
2. **El hash timbrado** — aparece en el verificador bajo **"Hash timbrado"** (una cadena de 64 caracteres). Es exactamente lo que freeTSA fechó.
3. **Los certificados públicos de freeTSA** — para comprobar que la firma es de ellos:
   - `https://freetsa.org/files/cacert.pem`  (certificado raíz)
   - `https://freetsa.org/files/tsa.crt`  (certificado de la autoridad de tiempo)

### Los comandos (copiar y pegar)

Necesita **OpenSSL** instalado (viene con macOS y Linux; en Windows, con Git for Windows). Ponga los cuatro archivos en una carpeta: el `.tsr` descargado, `cacert.pem`, `tsa.crt`.

**1) Ver qué dice el timbre** (fecha, autoridad, algoritmo):

```bash
openssl ts -reply -in timbre-DSR-0001.tsr -text
```

Verá, entre otras líneas:

```
Status: Granted.
Policy OID: tsa_policy1
Hash Algorithm: sha256
Time stamp: Sep  5 02:13:43 2026 GMT
TSA: .../O=Free TSA/.../CN=www.freetsa.org/...
```

Esa línea **`Time stamp`** es la fecha que un tercero atestigua. **`TSA: Free TSA`** es quién la atestigua.

**2) Verificar de verdad** que el timbre corresponde a ESE hash y lo firmó freeTSA:

```bash
openssl ts -verify \
  -digest <HASH_TIMBRADO> \
  -in timbre-DSR-0001.tsr \
  -CAfile cacert.pem \
  -untrusted tsa.crt
```

Cambie `<HASH_TIMBRADO>` por la cadena de 64 caracteres que muestra el verificador. La respuesta que busca es:

```
Verification: OK
```

**`Verification: OK`** significa: *este token fue emitido por freeTSA, sobre exactamente este hash, en la fecha que dice.* Si el hash no correspondiera, saldría **`Verification: FAILED`**.

### Ejemplo real (puede reproducirlo)

Para el reporte diario **DSR-0001** de la instalación de demostración, el hash timbrado es:

```
a6970bce396d182492a4af4c710cf96c100709334707620c5d4a563686fdfe6c
```

Y el comando de verificación completo:

```bash
openssl ts -verify \
  -digest a6970bce396d182492a4af4c710cf96c100709334707620c5d4a563686fdfe6c \
  -in timbre-DSR-0001.tsr \
  -CAfile cacert.pem \
  -untrusted tsa.crt
# → Verification: OK
```

> **Nota técnica menor:** OpenSSL imprime una advertencia *"certificate ... is not a CA cert"* sobre `tsa.crt`. Es normal y esperado (el certificado de la TSA es de "entidad final", se pasa como `-untrusted` a propósito). La verificación **igual dice `OK`**.
>
> **De dónde sale ese hash:** internamente es `SHA-256` de la huella del documento (el "hash timbrado" = SHA-256 del hash del sello). Usted no necesita recalcular nada: el verificador ya le da el valor exacto que va en `-digest`.

---

## Qué **NO** prueba cada cosa (la parte honesta)

Es tan importante como lo anterior. Ni el sello ni el timbre son magia:

- **El sello prueba integridad, no autoría ni veracidad.** Prueba que el documento **no cambió** desde que se emitió. **No** prueba quién lo redactó, ni que quien lo firmó tuviera autoridad para hacerlo, ni que lo que dice sea cierto.
- **El timbre prueba existencia en el tiempo, no veracidad.** Prueba que **ese contenido ya existía** en la fecha del timbre (y por tanto no se fabricó después). **No** prueba que el contenido sea verdadero.
- **En corto:** si el documento afirma una mentira, sigue siendo **una mentira sellada y timbrada**. El sello y el timbre garantizan que *esa misma mentira* no se cambió y que existía en tal fecha — nada más, y nada menos.

Lo que sí logran juntos: cierran la puerta a **alterar** un documento después de emitido y a **antedatar** uno fabricado más tarde. Para un accidente laboral, una inspección o un contrato, eso es justamente lo que suele estar en disputa.

---

## Marco legal (México)

Estos mecanismos se apoyan en el marco de comercio electrónico mexicano, ya definido:

- **Código de Comercio, art. 89** — reconoce los *mensajes de datos* y la *firma electrónica* en los actos de comercio: la información en medios electrónicos tiene efectos jurídicos.
- **Código de Comercio, art. 89 Bis** — *no se negarán efectos jurídicos, validez ni fuerza obligatoria* a la información por la sola razón de estar en un mensaje de datos. (Un documento electrónico no vale menos por ser electrónico.)
- **Código Civil Federal, art. 1811** — el consentimiento expresado por medios electrónicos es válido y **no requiere pacto previo** entre las partes para producir efectos.

**Sobre la NOM-151** (NOM-151-SCFI-2016, conservación de mensajes de datos y *constancia de conservación*): su adopción quedó **diferida**. La "constancia" de la NOM-151 exige un **Prestador de Servicios de Certificación (PSC)** acreditado en México; mientras eso se decide, CrewCare usa el **sello de tiempo RFC 3161** de una TSA (freeTSA), que es el mecanismo internacional equivalente para probar existencia en el tiempo. Cuando se opte por la NOM-151, el sello de tiempo actual **no estorba**: convive con ella.

> *Esta sección describe el marco en el que operan los mecanismos técnicos; no es asesoría legal. La valoración jurídica corresponde al profesional que lea este documento.*

---

## Resumen de una página

1. **¿Lo alteraron?** → Escanee el QR → verificador público de CrewCare → estado en pantalla.
2. **¿Existía en tal fecha?** → Descargue el `.tsr` → `openssl ts -verify -digest <hash> -in <archivo>.tsr -CAfile cacert.pem -untrusted tsa.crt` → `Verification: OK`.
3. **Los certificados de freeTSA** salen de `freetsa.org/files/` — no de CrewCare.
4. **Ninguno de los dos** prueba que el contenido sea *verdadero*: prueban que no se **alteró** ni se **antedató**.

*CrewCare · Salud y Seguridad — verificación de integridad y sello de tiempo de documentos.*
