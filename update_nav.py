import os

files = [
    "audiovisual.html",
    "aviso-privacidad.html",
    "branding.html",
    "confirmacion-compra.html",
    "contrato-servicios.html",
    "editorial.html",
    "invitacion-boda.html",
    "invitacion-evento.html",
    "invitacion-xv.html",
    "invitaciones.html",
    "marketing.html",
    "seo.html",
    "web-design.html"
]

desktop_target = '<a href="index.html#contact" class="hover:text-blue-400 transition-colors">Contacto</a>'
desktop_replace = '<a href="index.html#contact" class="hover:text-blue-400 transition-colors">Contacto</a>\n                <a href="noticias.html" class="hover:text-blue-400 transition-colors">Noticias</a>'

mobile_target = '<a href="index.html#contact" class="block hover:text-blue-400">Contacto</a>'
mobile_replace = '<a href="index.html#contact" class="block hover:text-blue-400">Contacto</a>\n                <a href="noticias.html" class="block hover:text-blue-400">Noticias</a>'

footer_target = '<a href="sitemap.xml" class="hover:text-white transition-colors">Sitemap</a>'
footer_replace = '<a href="sitemap.xml" class="hover:text-white transition-colors">Sitemap</a>\n                    <a href="noticias.html" class="hover:text-white transition-colors">Noticias</a>'

for filename in files:
    try:
        if not os.path.exists(filename):
            print(f"File not found: {filename}")
            continue

        with open(filename, 'r') as f:
            content = f.read()

        new_content = content
        
        # Desktop Menu
        if desktop_target in new_content:
            new_content = new_content.replace(desktop_target, desktop_replace)
        else:
            print(f"Desktop target not found in {filename}")

        # Mobile Menu
        if mobile_target in new_content:
            new_content = new_content.replace(mobile_target, mobile_replace)
        else:
            print(f"Mobile target not found in {filename}")

        # Footer
        if footer_target in new_content:
            new_content = new_content.replace(footer_target, footer_replace)
        else:
            print(f"Footer target not found in {filename}")

        if new_content != content:
            with open(filename, 'w') as f:
                f.write(new_content)
            print(f"Updated {filename}")
        else:
            print(f"No changes for {filename}")

    except Exception as e:
        print(f"Error processing {filename}: {e}")
