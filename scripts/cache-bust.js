#!/usr/bin/env node
/**
 * Pone una versión a final.css y script.js en todas las páginas.
 *
 * Por qué existe: el .htaccess cachea CSS y JS durante un mes. Sin versión en
 * la URL, los visitantes que ya habían entrado al sitio seguían viendo los
 * estilos viejos durante semanas, aunque el servidor ya tuviera los nuevos.
 * Pasó con la cuadrícula de logos: el HTML era nuevo y el CSS cacheado era el
 * del carrusel, así que las imágenes salían sin estilo.
 *
 * La versión es un hash del contenido del archivo, así que solo cambia cuando
 * el archivo cambia de verdad. Se ejecuta solo, como parte de `npm run build`.
 */

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const raiz = path.resolve(__dirname, '..');

/** Hash corto del contenido de un archivo. */
function version(archivo) {
    const ruta = path.join(raiz, archivo);
    if (!fs.existsSync(ruta)) return null;
    return crypto.createHash('sha1').update(fs.readFileSync(ruta)).digest('hex').slice(0, 8);
}

const versiones = {
    'final.css': version('final.css'),
    'script.js': version('script.js'),
};

// Páginas del sitio (no entra en node_modules ni subcarpetas)
const paginas = fs.readdirSync(raiz)
    .filter(f => /\.(html|php)$/.test(f))
    .filter(f => fs.statSync(path.join(raiz, f)).isFile());

let tocadas = 0;
const cambios = [];

for (const pagina of paginas) {
    const ruta = path.join(raiz, pagina);
    let texto = fs.readFileSync(ruta, 'utf8');
    const antes = texto;

    for (const [archivo, v] of Object.entries(versiones)) {
        if (!v) continue;
        const nombre = archivo.replace('.', '\\.');
        // Captura el archivo con o sin barra inicial y con o sin ?v= previo.
        const re = new RegExp(`((?:href|src)=")(/?${nombre})(\\?v=[^"]*)?(")`, 'g');
        texto = texto.replace(re, `$1$2?v=${v}$4`);
    }

    if (texto !== antes) {
        fs.writeFileSync(ruta, texto);
        tocadas++;
        cambios.push(pagina);
    }
}

const resumen = Object.entries(versiones)
    .filter(([, v]) => v)
    .map(([a, v]) => `${a}?v=${v}`)
    .join('  ');

console.log(`cache-bust: ${resumen}`);
console.log(`cache-bust: ${tocadas} página(s) actualizada(s)${tocadas ? ': ' + cambios.join(', ') : ''}`);
