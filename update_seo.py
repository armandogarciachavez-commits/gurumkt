import os
import re

files = [f for f in os.listdir('.') if f.endswith('.html')]

favicon_tag = '<link rel="icon" href="favicon.png" type="image/png">'
meta_desc_keyword = "Agencia de Publicidad Manzanillo"

def update_file(filename):
    try:
        with open(filename, 'r') as f:
            content = f.read()
        
        original_content = content
        
        # 1. Update Favicon
        if 'rel="icon"' not in content and 'rel="shortcut icon"' not in content:
            # Add before </head>
            if '</head>' in content:
                content = content.replace('</head>', f'    {favicon_tag}\n</head>')
            else:
                print(f"Warning: No </head> tag in {filename}")
        
        # 2. Update Meta Description
        # Regex to find meta description
        meta_pattern = re.compile(r'<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']', re.IGNORECASE)
        match = meta_pattern.search(content)
        
        if match:
            current_desc = match.group(1)
            if meta_desc_keyword not in current_desc:
                # Append keyword
                new_desc = f"{current_desc} - {meta_desc_keyword}"
                # Replace the content
                # We need to be careful to replace only this instance
                # Reconstruct the whole tag?
                # A safer way is modifying the group match in the string
                # Find the span of the content group
                start, end = match.span(1) # Span of the content inside quotes
                # Verify we are working on the string we read
                # To be safe, let's use replace on the precise string match if it's unique
                # Or reconstruct the line.
                
                # Let's simple string replace valid for the FIRST match found (which is usually the only one)
                full_tag = match.group(0)
                new_tag = full_tag.replace(current_desc, new_desc)
                content = content.replace(full_tag, new_tag)
                print(f"Updated description in {filename}")
            else:
                print(f"Keyword already in description in {filename}")
        else:
            # If no meta description, add it
            new_meta_tag = f'<meta name="description" content="{meta_desc_keyword}">'
            if '<title>' in content:
                # Try to add after title for tidiness
                content = content.replace('</title>', f'</title>\n    {new_meta_tag}')
                print(f"Added new meta description to {filename}")
            elif '</head>' in content:
                content = content.replace('</head>', f'    {new_meta_tag}\n</head>')
                print(f"Added new meta description (before head) to {filename}")

        if content != original_content:
            with open(filename, 'w') as f:
                f.write(content)
            print(f"Saved changes to {filename}")
        else:
            print(f"No changes for {filename}")

    except Exception as e:
        print(f"Error processing {filename}: {e}")

for f in files:
    update_file(f)
