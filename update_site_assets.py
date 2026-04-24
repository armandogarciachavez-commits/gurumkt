import os
import shutil
import re
import urllib.parse

# Configuration
IMG_DIR = 'img'
EXTENSIONS = {'.jpg', '.jpeg', '.png', '.webp', '.svg', '.gif'}
FAVICON_PATH = 'img/logo-normAsset 2.png'
# URL encode the favicon path for HTML
FAVICON_HREF = urllib.parse.quote(FAVICON_PATH) 
# Wait, urllib.quote encodes slash too? 
# "img/logo-normAsset 2.png" -> "img/logo-normAsset%202.png" is what we want.
# safe='/' keeps the slash.
FAVICON_HREF = "img/logo-normAsset%202.png"

META_KEYWORD = "Agencia de Publicidad Manzanillo"
ALT_KEYWORD = "Agencia de Publicidad en Manzanillo"

def is_image(filename):
    return any(filename.lower().endswith(ext) for ext in EXTENSIONS)

def main():
    # 1. Ensure img dir exists
    if not os.path.exists(IMG_DIR):
        os.makedirs(IMG_DIR)
        print(f"Created {IMG_DIR} directory.")

    # 2. Identify images to move
    root_files = [f for f in os.listdir('.') if os.path.isfile(f)]
    images_to_move = [f for f in root_files if is_image(f)]
    
    # We might have images that shouldn't be moved? 
    # The user said "todas las imagenes estan en la carpeta Guru... cambiar todas las imagenes a la cartepa img".
    # So we move all root images.
    
    moved_images = []
    for img in images_to_move:
        try:
            shutil.move(img, os.path.join(IMG_DIR, img))
            moved_images.append(img)
            print(f"Moved {img} to {IMG_DIR}/")
        except Exception as e:
            print(f"Error moving {img}: {e}")

    # 3. Update HTML files
    html_files = [f for f in os.listdir('.') if f.endswith('.html')]
    
    for html_file in html_files:
        try:
            with open(html_file, 'r') as f:
                content = f.read()
            
            original_content = content
            
            # A. Update Image References
            for img in moved_images:
                # Regex to find src="img" or src='img' NOT already preceded by img/
                # But simple replace is safer for filenames like "logo.png" -> "img/logo.png"
                # We must ensure we don't double replace if we run this twice, but since we just moved them, 
                # the file won't be in root anymore so subsequent runs won't find them in moved_images.
                # However, carefully replace:
                # src="logo.png" -> src="img/logo.png"
                # srcset="logo.png 500w" -> srcset="img/logo.png 500w"
                # url('logo.png') -> url('img/logo.png')
                
                # We can try a specialized replacement for src and srcset attributes to be safe.
                # Or just string replace verify boundary?
                # "logo.png" might be common text.
                # Let's target quotes: "logo.png" -> "img/logo.png"
                
                content = content.replace(f'"{img}"', f'"{IMG_DIR}/{img}"')
                content = content.replace(f"'{img}'", f"'{IMG_DIR}/{img}'")
                
                # Also content url() in style blocks?
                content = content.replace(f'({img})', f'({IMG_DIR}/{img})')

            # B. Add Favicon
            # Remove existing invalid favicons if any?
            # User wants to ADD it.
            if 'logo-normAsset' not in content:
                 # Check for head end
                if '</head>' in content:
                    # Check if any icon link exists and replace or append?
                    # Append is safer to ensure this one takes precedence or exists.
                    favicon_link = f'<link rel="icon" href="{FAVICON_HREF}" type="image/png">'
                    content = content.replace('</head>', f'    {favicon_link}\n</head>')
            
            # C. Update Meta Description
            # Same logic as before
            meta_pattern = re.compile(r'<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']', re.IGNORECASE)
            match = meta_pattern.search(content)
            if match:
                current_desc = match.group(1)
                if META_KEYWORD not in current_desc:
                    new_desc = f"{current_desc} - {META_KEYWORD}"
                    full_tag = match.group(0)
                    new_tag = full_tag.replace(current_desc, new_desc)
                    content = content.replace(full_tag, new_tag)
            else:
                new_meta_tag = f'<meta name="description" content="{META_KEYWORD}">'
                if '<title>' in content:
                    content = content.replace('</title>', f'</title>\n    {new_meta_tag}')
                elif '</head>' in content:
                    content = content.replace('</head>', f'    {new_meta_tag}\n</head>')

            # D. Update Alt Text
            # Find all img tags
            # We use regex to find img tags and check attributes
            # <img ... >
            
            def alt_updater(match):
                img_tag = match.group(0)
                # Check if alt exists
                alt_match = re.search(r'alt=["\'](.*?)["\']', img_tag)
                
                if alt_match:
                    current_alt = alt_match.group(1)
                    if ALT_KEYWORD not in current_alt:
                        # Append
                        if current_alt.strip():
                            new_alt = f"{current_alt} - {ALT_KEYWORD}"
                        else:
                            new_alt = ALT_KEYWORD
                        
                        # Replace in the tag
                        # We need to be careful with quotes
                        quote = img_tag[alt_match.start():alt_match.end()][4] # current quote char
                        return img_tag.replace(f'alt={quote}{current_alt}{quote}', f'alt={quote}{new_alt}{quote}')
                    else:
                        return img_tag
                else:
                    # No alt tag, insert it before the closing >
                    # Handle /> or >
                    if '/>' in img_tag:
                        return img_tag.replace('/>', f' alt="{ALT_KEYWORD}" />')
                    else:
                        return img_tag.replace('>', f' alt="{ALT_KEYWORD}">')

            # Regex for img tag
            content = re.sub(r'<img\s+[^>]*?>', alt_updater, content)

            if content != original_content:
                with open(html_file, 'w') as f:
                    f.write(content)
                print(f"Updated {html_file}")
            else:
                print(f"No changes for {html_file}")

        except Exception as e:
            print(f"Error processing {html_file}: {e}")

if __name__ == "__main__":
    main()
