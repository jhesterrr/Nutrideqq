import re

with open('anthropometric-information.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Update the Hero Section
header_snippet = r'''<div class="header">.*?<div class="header-actions">.*?</div>.*?</div>'''
new_hero = '''<!-- ═══ HERO BANNER ═══ -->
                <?php if (): ?>
                <div class="ai-hero">
                    <div class="ai-hero-avatar"><?= htmlspecialchars(strtoupper(substr(['name'], 0, 1))) ?></div>
                    <div class="ai-hero-info">
                        <h1 class="ai-hero-name"><?= htmlspecialchars(['name']) ?></h1>
                        <p class="ai-hero-subtitle">
                            <?= ['age'] ? ['age'].' yrs' : '' ?>
                            <?= ['gender'] ? ' · '.ucfirst(['gender']) : '' ?>
                            <?= ['city'] ? ' · '.htmlspecialchars(['city']) : '' ?>
                        </p>
                        <div class="ai-hero-badges">
                            <span class="ai-badge"><i class="fas fa-weight"></i> <?= ['weight'] ?? 'No weight' ?> kg</span>
                            <span class="ai-badge"><i class="fas fa-ruler-vertical"></i> <?= ['height'] ?? 'No height' ?> cm</span>
                            <span class="ai-badge"><i class="fas fa-circle-check"></i> Active</span>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="ai-hero">
                    <div class="ai-hero-avatar" style="background:rgba(255,255,255,0.12);"><i class="fas fa-user-md" style="font-size:1.4rem;"></i></div>
                    <div class="ai-hero-info">
                        <h1 class="ai-hero-name">Anthropometric Information</h1>
                        <p class="ai-hero-subtitle">Select a client below to view and manage their health data</p>
                    </div>
                </div>
                <?php endif; ?>'''
content = re.sub(header_snippet, new_hero, content, flags=re.DOTALL)

# 2. Update the Tabs structure
tabs_snippet = r'''<div class="tabs-container">\s*<div class="tabs-header">(.*?)</div>'''
new_tabs = '''<div class="ai-tabs-wrap tabs-container">
                        <div class="ai-tab-rail">\\1</div>'''
content = re.sub(tabs_snippet, new_tabs, content, flags=re.DOTALL)

# 3. Update the form-group fields
content = content.replace('class="form-group"', 'class="ai-field"')
content = content.replace('class="form-control"', 'class="ai-field-inner input"')

# 4. FIX Choices.js Initialization
js_target = r'''choicesInstances\[key\] = new Choices\(select, \{\s*searchEnabled: true,\s*itemSelectText: '',\s*shouldSort: false,\s*placeholder: true\s*\}\);'''
js_replace = '''choicesInstances[key] = new Choices(select, {
                            searchEnabled: true,
                            itemSelectText: '',
                            shouldSort: false,
                            placeholder: true,
                            position: 'bottom'
                        });
                        
                        select.addEventListener('showDropdown', function() {
                            const tr = select.closest('tr');
                            if (tr) { tr.style.position = 'relative'; tr.style.zIndex = '9999'; }
                        });
                        select.addEventListener('hideDropdown', function() {
                            const tr = select.closest('tr');
                            if (tr) { tr.style.position = ''; tr.style.zIndex = ''; }
                        });'''
content = re.sub(js_target, js_replace, content)

with open('anthropometric-information.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Patch applied successfully via python regex!")
