/** Real installed Joomla/Sources editor smoke test; preview requests only, never saves/sends. */
import {createRequire} from 'node:module';
import {resolve,dirname} from 'node:path';
import {fileURLToPath} from 'node:url';
import {spawnSync} from 'node:child_process';
import assert from 'node:assert/strict';
const root=resolve(dirname(fileURLToPath(import.meta.url)),'..');
const require=createRequire(resolve(root,'../pkg_sources/package.json'));const {chromium}=require('playwright');
const container=process.env.JOOMLA_CONTAINER||'catichisi-local-web-1',base=process.env.JOOMLA_URL||'http://localhost:8089';
function cli(...args){const r=spawnSync('docker',args,{encoding:'utf8'});if(r.status!==0)throw Error(r.stdout+r.stderr);return r.stdout.replace(/^\uFEFF/,'').trim();}
cli('cp',resolve(root,'tests/editor-web-fixture.php'),container+':/tmp/newsletterbridge-editor-web-fixture.php');
const fixture=JSON.parse(cli('exec','--user','www-data',container,'php','/tmp/newsletterbridge-editor-web-fixture.php'));
let browser;
try{
    browser=await chromium.launch({executablePath:process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
    for(const administrator of [false,true]){
        const page=await browser.newPage({viewport:{width:1280,height:960}});
        await page.goto(base+(administrator?'/administrator/':'/index.php?option=com_users&view=login'));
        const username=page.locator('input[name="username"]').first();
        await username.fill(fixture.username);
        await page.locator('input[name="password"],input[name="passwd"]').first().fill(fixture.password);
        const form=username.locator('xpath=ancestor::form');
        await Promise.all([page.waitForNavigation({waitUntil:'networkidle'}),form.locator('button[type="submit"],input[type="submit"]').first().click()]);
        await page.goto(base+(administrator?'/administrator/index.php':'/index.php')+'?option=com_newsletterbridge&view=newsletter');
        try { await page.waitForFunction(()=>window.tinymce?.get('jform_body')?.initialized); }
        catch { throw new Error('Editor not initialised: '+page.url()+' '+await page.title()+' '+(await page.locator('body').innerText()).slice(0,1200)); }
        assert.equal(await page.evaluate(()=>Joomla.getOptions('plg_editorsxtd_sourcesbutton.preview').editors.jform_body.presentation),'email');
        await page.waitForFunction(()=>window.SuperSoftSourcesPreview?.getPresentation('jform_body')==='email');
        for(const layout of ['cards','details','list','icons','contents']){
            const token='{sources:'+JSON.stringify({version:2,display:'separate',ordering:{mode:'blocks',order:'published-desc'},presentation:{title:'',layout:'cards',limit:18},blocks:[{type:'articles',title:'',articles:[fixture.article],filters:{tags:[],categories:[],order:'published-desc',limit:0},presentation:{layout}}]})+'}';
            await page.evaluate(token=>tinymce.get('jform_body').setContent('<p>'+token+'</p>'),token);
            // Trigger Sources' own live-mode button, without private host integration.
            await page.waitForFunction(()=>tinymce.get('jform_body').getDoc().querySelector('[data-sources-mode="render"]'));
            const responsePromise=page.waitForResponse(async r=>r.request().method()==='POST'&&r.request().postData()?.includes('presentation=email'));
            await page.evaluate(()=>tinymce.get('jform_body').getDoc().querySelector('[data-sources-mode="render"]').click());
            const response=await responsePromise;assert.equal(response.status(),200);
            const result=await response.json();const record=result.data[0];
            assert.equal(record.presentation,'email');assert.equal(record.renderer,'EmailRenderer');assert.equal(record.purpose,'preview');assert.deepEqual(record.styles,[]);assert.equal(record.ok,true);
            await page.waitForFunction(()=>tinymce.get('jform_body').getDoc().querySelector('iframe.sources-email-preview'));
            const saved=await page.evaluate(()=>tinymce.get('jform_body').getContent());
            assert.ok(saved.includes(token));assert.ok(!saved.includes('sources-email-preview'));
        }
        assert.equal(await page.locator('[data-nb-email-preview-status]').isHidden(),true);
        console.log((administrator?'Backend':'Frontend')+': actual Sources 4.2 email AJAX for five layouts; token-only GetContent. No save/send.');
        await page.close();
    }
}finally{
    if(browser)await browser.close();
    console.log(cli('exec','--user','www-data',container,'php','/tmp/newsletterbridge-editor-web-fixture.php','cleanup'));
}
