const { chromium } = require('playwright');

async function testFA() {
    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext();
    const page = await context.newPage();

    page.on('console', msg => console.log('CONSOLE:', msg.text()));
    page.on('pageerror', err => console.log('PAGE ERROR:', err.message));

    try {
        await page.goto('http://192.168.1.88:8080/', { waitUntil: 'networkidle' });
        await page.fill('input[name="user_name_entry_field"]', 'opencode');
        await page.fill('input[name="password"]', 'opencode');
        await page.selectOption('select[name="company_login_name"]', '0');
        await page.click('input[type="submit"]');
        await page.waitForLoadState('networkidle');

        await page.goto('http://192.168.1.88:8080/admin/inst_module.php', { waitUntil: 'networkidle' });
        
        // Select company
        const extsetSelect1 = await page.$('select[name="extset"]');
        if (extsetSelect1) {
            await extsetSelect1.selectOption('0');
            await page.waitForLoadState('networkidle');
        }
        
        // Check current status
        console.log('--- Current module status ---');
        const modules = [
            { name: 'ksf_FA_Square', activeName: 'Active9' },
            { name: 'ksf_FA_Notes', activeName: 'Active19' },
            { name: 'ksf_FA_Common', activeName: 'Active20' }
        ];
        
        for (const mod of modules) {
            const checkbox = await page.$(`input[name="${mod.activeName}"]`);
            if (checkbox) {
                const checked = await checkbox.isChecked();
                console.log(`${mod.name} (${mod.activeName}): checked = ${checked}`);
            } else {
                console.log(`${mod.name} (${mod.activeName}): NOT FOUND`);
            }
        }
        
        // Activate Common (Active20)
        console.log('\n--- Activating ksf_FA_Common ---');
        const commonCheckbox = await page.$('input[name="Active20"]');
        if (commonCheckbox) {
            const checked = await commonCheckbox.isChecked();
            if (!checked) {
                console.log('Checking Active20...');
                await page.check('input[name="Active20"]');
                await page.waitForTimeout(500);
            } else {
                console.log('Already checked');
            }
        }
        
        // Check form and button
        const form = await page.$('form');
        const button = await page.$('button[name="Refresh"][value="Update"]');
        console.log('\nForm found:', !!form);
        console.log('Button found:', !!button);
        
        if (button) {
            const btnInfo = await button.evaluate(btn => ({
                tagName: btn.tagName,
                type: btn.type,
                name: btn.name,
                value: btn.value,
                form: btn.form ? 'yes' : 'no',
                onclick: btn.onclick ? btn.onclick.toString().substring(0, 100) : 'none'
            }));
            console.log('Button info:', JSON.stringify(btnInfo, null, 2));
        }
        
        if (form) {
            const formInfo = await form.evaluate(f => ({
                action: f.action,
                method: f.method,
                inputs: Array.from(f.querySelectorAll('input')).map(i => ({
                    name: i.name,
                    type: i.type,
                    value: i.value,
                    checked: i.checked
                }))
            }));
            console.log('Form info:', JSON.stringify(formInfo, null, 2));
        }
        
        // Try submitting via form submit
        console.log('\nSubmitting form via JavaScript...');
        await page.evaluate(() => {
            const form = document.querySelector('form');
            if (form) {
                form.submit();
            }
        });
        
        console.log('Waiting for navigation...');
        try {
            await page.waitForNavigation({ waitUntil: 'networkidle', timeout: 10000 });
            console.log('Navigation completed');
        } catch (e) {
            console.log('No navigation, waiting for load state...');
            await page.waitForLoadState('networkidle');
        }
        await page.waitForTimeout(2000);
        
        // Verify
        console.log('\n--- Verifying status after update ---');
        await page.goto('http://192.168.1.88:8080/admin/inst_module.php', { waitUntil: 'networkidle' });
        
        const extsetSelect2 = await page.$('select[name="extset"]');
        if (extsetSelect2) {
            await extsetSelect2.selectOption('0');
            await page.waitForLoadState('networkidle');
        }
        
        const modules2 = [
            { name: 'ksf_FA_Square', activeName: 'Active9' },
            { name: 'ksf_FA_Notes', activeName: 'Active19' },
            { name: 'ksf_FA_Common', activeName: 'Active20' }
        ];
        
        for (const mod of modules2) {
            const checkbox = await page.$(`input[name="${mod.activeName}"]`);
            if (checkbox) {
                const checked = await checkbox.isChecked();
                console.log(`${mod.name} (${mod.activeName}): checked = ${checked}`);
            }
        }
        
        const commonCheckboxFinal = await page.$('input[name="Active20"]');
        if (commonCheckboxFinal) {
            const checked = await commonCheckboxFinal.isChecked();
            console.log(`Common (Active20) final: checked = ${checked}`);
        }
        
        await page.waitForTimeout(30000);

    } catch (err) {
        console.error('Error:', err);
    } finally {
        await browser.close();
    }
}

testFA().catch(console.error);