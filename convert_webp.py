import os
from PIL import Image

def convert_to_webp(directory):
    for root, dirs, files in os.walk(directory):
        for file in files:
            if file.lower().endswith(('.jpg', '.jpeg', '.png')) and not file.lower().endswith('.webp'):
                file_path = os.path.join(root, file)
                webp_path = os.path.splitext(file_path)[0] + ".webp"
                
                try:
                    with Image.open(file_path) as img:
                        img.save(webp_path, "WEBP", quality=85)
                        print(f"Converted: {file} -> {os.path.basename(webp_path)}")
                except Exception as e:
                    print(f"Failed to convert {file}: {e}")

if __name__ == "__main__":
    current_dir = os.getcwd()
    print(f"Scanning directory: {current_dir}")
    convert_to_webp(current_dir)
