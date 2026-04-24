import os
import shutil
import re

# Configuration
ROOT_DIR = '/Users/armandogarciachavez/Desktop/Guru Nuevo '
TARGET_DIR = os.path.join(ROOT_DIR, 'img')
MOVED_DIRS = ['Logos Clientes', 'Sitios Web', 'Editorial', 'Publicaciones', 'parabodas', 'quinceanera']
IMG_EXTS = {'.jpg', '.jpeg', '.png', '.webp', '.gif', '.svg'}
FAVICON_FILENAME = 'logo-normAsset 2.png'
ALT_TEXT = "Agencia de Publicidad en Manzanillo"

def main():
    print("Starting SEO and Organization Task...")

    if not os.path.exists(TARGET_DIR):
        os.makedirs(TARGET_DIR)
        print(f"Created directory: {TARGET_DIR}")

    # 1. Move Directories
    for d in MOVED_DIRS:
        src_path = os.path.join(ROOT_DIR, d)
        dst_path = os.path.join(TARGET_DIR, d)
        
        if os.path.exists(src_path):
            print(f"Moving directory: {d} -> img/{d}")
            if os.path.exists(dst_path):
                print(f"Warning: Destination {dst_path} already exists. Merging contents...")
                for item in os.listdir(src_path):
                    s = os.path.join(src_path, item)
                    d_item = os.path.join(dst_path, item)
                    if os.path.exists(d_item):
                        continue
                    shutil.move(s, d_item)
                shutil.rmtree(src_path)
            else:
                shutil.move(src_path, dst_path)

    # 2. Move Loose Images
    moved_files = []
    for filename in os.listdir(ROOT_DIR):
        if filename == 'img' or filename.startswith('.'):
            continue
            
        ext = os.path.splitext(filename)[1].lower()
        if ext in IMG_EXTS:
            src_path = os.path.join(ROOT_DIR, filename)
            dst_path = os.path.join(TARGET_DIR, filename)
            
            # Special case: The favicon is likely 'logo-normAsset 2.png'. 
            # If we move it, we must ensure the HTML update knows it's moved.
            
            if not os.path.exists(dst_path):
                print(f"Moving file: {filename} -> img/{filename}")
                shutil.move(src_path, dst_path)
                moved_files.append(filename)
            else:
                print(f"Skipping {filename}, already exists in img/")
                # If it's already there (e.g. from previous run), we still need to update HTML for it
                moved_files.append(filename)

    # 3. Update HTML Files
    # Root files
    for filename in os.listdir(ROOT_DIR):
        if filename.endswith('.html'):
            filepath = os.path.join(ROOT_DIR, filename)
            process_html_file(filepath, moved_files)
            
    # Subdirectories
    for root, dirs, files in os.walk(ROOT_DIR):
        if 'node_modules' in root:
            continue
        if root == ROOT_DIR:
            continue
        for filename in files:
            if filename.endswith('.html'):
                filepath = os.path.join(root, filename)
                process_html_file(filepath, moved_files)

def process_html_file(filepath, moved_files):
    print(f"Processing HTML: {filepath}")
    
    # Calculate favicon path relative to this file
    # If file is in ROOT, it should be "img/logo..."
    # If file is in ROOT/GuruWeb3.0, it should be "../img/logo..."
    rel_path_to_img_folder = os.path.relpath(TARGET_DIR, os.path.dirname(filepath))
    rel_path_to_img_folder = rel_path_to_img_folder.replace(os.sep, '/')
    
    # Ensure it ends with / unless it's empty (which relies on . behavior)
    # relpath to sibling is usually "../img". To same dir is ".".
    if rel_path_to_img_folder == '.':
        # This occurs if filepath is inside img/ folder? Unlikely for HTML files.
        # But if filepath is in ROOT, TARGET is ROOT/img. relpath is 'img'.
        path_prefix = 'img/'
    else:
        path_prefix = rel_path_to_img_folder + '/'

    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    original_content = content

    # A. Update Paths for Moved Directories
    for d in MOVED_DIRS:
        # Match src="[../]*FolderName/" and inject img/
        # Capture group 1: src=" or srcset="
        # Capture group 2: quote
        # Capture group 3: relative path prefix (../ or ./)
        pattern = re.compile(r'(src|srcset)=([\"\'])((?:\.\./)*)' + re.escape(d) + r'/', re.IGNORECASE)
        # Replacement: \1=\2\3img/FolderName/
        content = pattern.sub(r'\1=\2\3img/' + d + '/', content)

    # B. Update Paths for Moved Files
    for fname in moved_files:
        # Match src="[../]*filename"
        pattern = re.compile(r'(src|srcset)=([\"\'])((?:\.\./)*)' + re.escape(fname) + r'([\"\'])', re.IGNORECASE)
        content = pattern.sub(r'\1=\2\3img/' + fname + r'\4', content)

    # C. Update Alt Text
    # 1. Replace existing alt attributes
    def repl_alt(match):
        # preserve the tag, replace just the alt value
        # This is simple string replacement within the match
        return re.sub(r'alt=([\"\']).*?\1', f'alt="{ALT_TEXT}"', match.group(0))
    
    content = re.sub(r'<img\s+[^>]*?alt=[\"\'].*?[\"\'][^>]*?>', repl_alt, content)

    # 2. Add missing alt attributes
    def add_alt(match):
        return match.group(0).rstrip('>').rstrip('/') + f' alt="{ALT_TEXT}">'
    
    # Match img tags that DO NOT have "alt="
    content = re.sub(r'<img\s+(?![^>]*\balt=)[^>]*?>', add_alt, content)

    # D. Add Favicon
    favicon_href = path_prefix + FAVICON_FILENAME
    # URL encode spaces
    favicon_href = favicon_href.replace(' ', '%20')
    
    if 'rel="icon"' not in content and 'rel="shortcut icon"' not in content:
        link_tag = f'    <link rel="icon" href="{favicon_href}">'
        if '</head>' in content:
            content = content.replace('</head>', f'{link_tag}\n</head>')
    
    if content != original_content:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"Updated {filepath}")
    else:
        print(f"No changes for {filepath}")

if __name__ == '__main__':
    main()
