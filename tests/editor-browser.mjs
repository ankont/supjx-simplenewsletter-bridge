/** Opt-in real-editor UI fixture. Reads Sources 4.2 sources/assets; never modifies that repo. */
import {createRequire} from 'node:module';
import {readFile} from 'node:fs/promises';
import {createServer} from 'node:http';
import {resolve,dirname,extname} from 'node:path';
import {fileURLToPath} from 'node:url';
import assert from 'node:assert/strict';
const root=resolve(dirname(fileURLToPath(import.meta.url)),'..');
const sourcesRepo=resolve(process.argv[2]||resolve(root,'../pkg_sources'));
const require=createRequire(resolve(sourcesRepo,'package.json'));
const {chromium}=require('playwright');
const sources=resolve(sourcesRepo,'package/plugins/editors-xtd/sourcesbutton/media');
const joomla=resolve(process.argv[3]||resolve(root,'../../JoomlaTest/site'));
const hostScript=await readFile(resolve(root,'source/media/js/editor-presentation.js'),'utf8');
const modes=['cards','details','list','icons','contents'];
const fixtures=new Map();
for(const mode of modes) fixtures.set(mode,await readFile(resolve(sourcesRepo,'tests/artifacts/email-'+mode+'.html'),'utf8'));
let requests=[];
const server=createServer(async(req,res)=>{
  try{
    const url=new URL(req.url,'http://localhost'); let body,mime='text/html';
    if(url.pathname==='/'){
      const old=url.searchParams.has('missing'),jce=url.searchParams.has('jce'),backend=url.searchParams.get('backend')==='true';
      const config={
        'com_newsletterbridge.editorPresentation':{editors:{'mail-one':'email','mail-two':'email'}},
        'plg_editorsxtd_sourcesbutton.preview':{editors:{article:{presentation:'site'},'mail-one':{presentation:'email'},'mail-two':{presentation:'email'}}},
        'plg_editorsxtd_sourcesbutton.inline':{previewMode:'render',badgeLabel:'Sources',csrfToken:'fixture',ajaxUrl:'/preview',contentCss:'/sources/css/sourcesbutton.css'},
      };
      body=`<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="icon" href="data:,"><link rel="stylesheet" href="/joomla/media/templates/${backend?'administrator/atum':'site/cassiopeia'}/css/template.css"><script type="importmap">{"imports":{"editor-api":"/joomla/media/system/js/editors/editor-api.js","editor-decorator":"/joomla/media/system/js/editors/editor-decorator.js"}}</script>
      <script>window.options=${JSON.stringify(config)};window.Joomla={getOptions:(key,fallback)=>options[key]??fallback??{},Text:{_:k=>k},editors:{instances:{}}};</script>
      <script src="/joomla/media/${jce?'plg_editors_jce/tinymce':'vendor/tinymce'}/tinymce.js"></script>
      <script defer src="/host.js"></script><body>
      <div data-nb-editor id="host-one"><p data-nb-email-preview-status>Email presentation not confirmed</p><textarea id="mail-one"></textarea></div>
      <div data-nb-editor id="host-two"><p data-nb-email-preview-status>Email presentation not confirmed</p><textarea id="mail-two"></textarea></div>
      <div><textarea id="article"></textarea></div>
      <script type="module">
      window.inline=await import('/sources/js/inline.js');
      ${old?'':"await import('/sources/js/sourcesbutton.js');"}
      window.instances={};window.startEditor=id=>new Promise(done=>{
        const ready=ed=>{instances[id]=ed;done();};
        ${jce?"tinymce.init({mode:'exact',elements:id,theme:'core',compress:{css:false},plugins:'noneditable',theme_advanced_buttons1:'bold,italic',theme_advanced_buttons2:'',theme_advanced_buttons3:'',height:400,setup:ed=>ed.onInit.add(()=>ready(ed))});":"tinymce.init({selector:'#'+id,license_key:'gpl',plugins:'code',toolbar:'code',height:400,setup:ed=>ed.on('init',()=>ready(ed))});"}
      });
      for(const id of ['mail-one','mail-two','article'])await startEditor(id);
      window.ready=true;</script>`;
    }else if(url.pathname==='/host.js'){body=hostScript;mime='text/javascript';}
    else if(url.pathname==='/preview'){
      let raw='';for await(const chunk of req)raw+=chunk;
      const data=new URLSearchParams(raw);requests.push({presentation:data.get('presentation'),mode:data.get('mode')});
      const config=JSON.parse(data.get('configuration'));
      const mode=config.blocks[0].presentation.layout;
      const email=data.get('presentation')==='email';
      body=JSON.stringify({success:true,data:[{ok:true,presentation:email?'email':'site',purpose:'preview',renderer:email?'EmailRenderer':'InlineRenderer',styles:[],html:email?fixtures.get(mode):'<p>Site-only presentation</p>'}]});mime='application/json';
    }else{
      const base=url.pathname.startsWith('/sources/')?sources:url.pathname.startsWith('/joomla/')?joomla:null;
      if(!base)throw Error('Unknown fixture URL');
      const path=resolve(base,url.pathname.split('/').slice(2).join('/'));
      if(!path.startsWith(base+'/'.replace('/',process.platform==='win32'?'\\':'/')))throw Error('Invalid path');
      body=await readFile(path);mime=({'.js':'text/javascript','.css':'text/css','.woff2':'font/woff2','.svg':'image/svg+xml','.png':'image/png'})[extname(path)]||'application/octet-stream';
    }
    res.writeHead(200,{'Content-Type':mime+'; charset=utf-8'});res.end(body);
  }catch{res.writeHead(404);res.end();}
});
await new Promise(r=>server.listen(0,'127.0.0.1',r));
const base='http://127.0.0.1:'+server.address().port;
const browser=await chromium.launch({executablePath:process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
let scenarios=0;
try{
  for(const backend of [false,true])for(const jce of [false,true])for(const width of [1280,390]){
    requests=[];
    const page=await browser.newPage({viewport:{width,height:900}}),errors=[];
    page.on('pageerror',e=>errors.push(e.message));
    await page.goto(base+'/?backend='+backend+(jce?'&jce=1':''));await page.waitForFunction(()=>window.ready);
    assert.equal(await page.evaluate(()=>SuperSoftSourcesPreview.getPresentation('article')),'site');
    for(const mode of modes){
      const token=await page.evaluate(mode=>{
        const c=inline.newContainer();c.blocks[0].articles=[1];c.blocks[0].presentation.layout=mode;
        const raw=inline.serialize(c);instances['mail-one'].setContent('<p>'+raw+'</p>');return raw;
      },mode);
      await page.waitForFunction(()=>instances['mail-one'].getDoc().querySelector('iframe.sources-email-preview')?.contentDocument?.querySelector('table[role=presentation]'));
      const imageWidth=await page.evaluate(()=>instances['mail-one'].getDoc().querySelector('iframe.sources-email-preview').contentDocument.querySelector('img')?.getAttribute('width'));
      assert.equal(imageWidth,String({cards:264,details:146,list:50,icons:120,contents:560}[mode]));
      const saved=await page.evaluate(()=>instances['mail-one'].getContent());
      assert.ok(saved.includes(token));assert.ok(!saved.includes('iframe'));assert.ok(!saved.includes('data-sources-token'));
      assert.equal(requests.at(-1).presentation,'email');
    }
    await page.evaluate(()=>{
      const c=inline.newContainer();c.blocks[0].articles=[1];
      instances['mail-two'].setContent(inline.serialize(c));instances.article.setContent(inline.serialize(c));
    });
    await page.waitForFunction(()=>instances['mail-two'].getDoc().querySelector('iframe.sources-email-preview')&&instances.article.getDoc().body.textContent.includes('Site-only'));
    const status=await page.locator('#host-one [data-nb-email-preview-status]').isHidden();assert.equal(status,true);
    await page.evaluate(()=>instances['mail-one'].remove());
    await page.evaluate(()=>startEditor('mail-one'));
    await page.waitForFunction(()=>instances['mail-one'].getDoc().querySelector('iframe.sources-email-preview'));
    assert.equal(await page.evaluate(()=>SuperSoftSourcesPreview.getPresentation('mail-one')),'email');
    await page.evaluate(()=>{instances['mail-one'].remove();window.detached=document.querySelector('#host-one');detached.remove();});
    await page.waitForFunction(()=>!options['plg_editorsxtd_sourcesbutton.preview'].editors['mail-one']);
    await page.evaluate(()=>document.body.append(detached));
    await page.waitForFunction(()=>options['plg_editorsxtd_sourcesbutton.preview'].editors['mail-one']?.presentation==='email');
    await page.evaluate(()=>window.dispatchEvent(new PageTransitionEvent('pagehide')));
    assert.equal(await page.evaluate(()=>options['plg_editorsxtd_sourcesbutton.preview'].editors['mail-two']),undefined);
    await page.evaluate(()=>window.dispatchEvent(new PageTransitionEvent('pageshow')));
    assert.equal(await page.evaluate(()=>SuperSoftSourcesPreview.getPresentation('mail-two')),'email');
    assert.equal(await page.evaluate(()=>SuperSoftSourcesPreview.getPresentation('article')),'site');
    assert.deepEqual(errors,[]);await page.close();scenarios++;
  }
  for(const jce of [false,true]){
    const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
    await page.goto(base+'/?missing=1'+(jce?'&jce=1':''));await page.waitForFunction(()=>window.ready);
    await page.evaluate(()=>instances['mail-one'].setContent('<p>Still editable {sources:raw}</p>'));
    assert.equal(await page.locator('#host-one [data-nb-email-preview-status]').isVisible(),true);
    assert.ok((await page.evaluate(()=>instances['mail-one'].getContent())).includes('{sources:raw}'));
    assert.deepEqual(errors,[]);await page.close();scenarios++;
  }
  console.log(`PASS: ${scenarios} real TinyMCE/JCE client fixtures; five layouts, instance isolation, reinit/removal and missing Sources. AJAX is mocked; no mail.`);
}finally{await browser.close();await new Promise(r=>server.close(r));}
