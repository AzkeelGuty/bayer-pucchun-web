/* Cálculos de captura de productos con precisión de céntimos.
   El servidor vuelve a realizar el mismo cálculo antes de guardar. */
(function () {
    'use strict';

    function quantity(raw) {
        const value=String(raw??'').trim();
        if(!/^\d+(?:\.0{1,3})?$/.test(value))return null;
        const normalized=value.split('.')[0].replace(/^0+(?=\d)/,'');
        const number=Number(normalized);
        return Number.isSafeInteger(number)&&number>=1&&number<=99999999?number:null;
    }
    function priceCents(raw) {
        const value=String(raw??'').trim();
        if(!/^\d{1,12}(?:\.\d{1,2})?$/.test(value))return null;
        const [whole,decimals='']=value.split('.');
        const number=Number(whole)*100+Number(decimals.padEnd(2,'0'));
        return Number.isSafeInteger(number)?number:null;
    }
    function money(cents) {
        if(cents===null||!Number.isSafeInteger(cents))return '—';
        const amount=(cents/100).toFixed(2).split('.');
        return 'S/ '+amount[0].replace(/\B(?=(\d{3})+(?!\d))/g,',')+'.'+amount[1];
    }

    function calculate(lines,mode) {
        const data=[];
        const quantities={};
        let amountSum=0,baseSum=0,taxSum=0,totalSum=0,hasPrice=false,missingPrice=false,invalid=false;
        for(const line of lines) {
            const q=quantity(line.cantidad);
            const raw=line.valor_unitario;
            const cents=raw===null||raw===undefined||String(raw).trim()===''?null:priceCents(raw);
            const unit=String(line.unidad||'Unidades').trim()||'Unidades';
            if(q!==null)quantities[unit]=(quantities[unit]||0)+q;
            if(q===null){invalid=true;data.push({importe:null});continue;}
            if(cents===null){
                if(raw!==null&&raw!==undefined&&String(raw).trim()!=='')invalid=true;
                missingPrice=true;data.push({importe:null});continue;
            }
            hasPrice=true;
            const amount=q*cents;
            if(!Number.isSafeInteger(amount)||amount>90000000000000){invalid=true;data.push({importe:null});continue;}
            let base=amount,igv=0,total=amount;
            if(mode==='INCLUIDO'){
                base=Math.floor((amount*100+59)/118);
                igv=amount-base;
            }else if(mode==='ADICIONAL'){
                igv=Math.floor((amount*18+50)/100);
                total=amount+igv;
            }
            data.push({importe:amount,subtotal:base,igv,total});
            amountSum+=amount;baseSum+=base;taxSum+=igv;totalSum+=total;
        }
        if(![amountSum,baseSum,taxSum,totalSum].every(Number.isSafeInteger))invalid=true;
        return {
            lines:data,quantities,items:lines.length,
            hasPrice,missingPrice,invalid,mode,
            subtotal:hasPrice&&mode?baseSum:null,
            igv:hasPrice&&mode?taxSum:null,
            total:hasPrice&&!invalid?(mode?totalSum:amountSum):null,
        };
    }

    function initForm(form) {
        if(form.dataset.commercialInitialized==='1')return;
        const summary=form.querySelector('[data-commercial-totals]');
        const body=form.querySelector('[data-details-body]')||form.querySelector('#detalle-table tbody');
        if(!summary||!body)return;
        form.dataset.commercialInitialized='1';
        const modeSelect=form.querySelector('[data-igv-mode]');
        const isGuide=Boolean(form.querySelector('#detalle-table'));
        let pending=false;
        function render() {
            pending=false;
            if(!form.isConnected)return;
            const rows=Array.from(body.querySelectorAll('tr'));
            const mode=modeSelect?.value||null;
            const lines=rows.map(row=>{
                const unit=row.querySelector('select[name$="[unidad_id]"]');
                const selected=unit?.selectedOptions?.[0];
                return {
                    cantidad:row.querySelector('input[name$="[cantidad]"]')?.value||'',
                    valor_unitario:row.querySelector('input[name$="[valor_unitario]"]')?.value??null,
                    unidad:selected?.value?(selected.textContent?.trim()||'Unidades'):'Unidades'
                };
            });
            const totals=calculate(lines,mode);
            rows.forEach((row,index)=>{
                const el=row.querySelector('[data-line-amount]');
                if(el)el.textContent=money(totals.lines[index]?.importe??null);
            });
            const set=(name,value)=>{
                const target=summary.querySelector('[data-'+name+']');
                if(target)target.textContent=value;
            };
            set('total-items',totals.items+' '+(totals.items===1?'producto':'productos'));
            const qtyContainer=summary.querySelector('[data-unit-quantities]');
            if(qtyContainer){
                qtyContainer.replaceChildren();
                for(const [unit,q] of Object.entries(totals.quantities)){
                    const badge=document.createElement('span');
                    badge.className='commercial-unit-total';
                    badge.textContent=q+' '+unit;
                    qtyContainer.appendChild(badge);
                }
                if(!Object.keys(totals.quantities).length)qtyContainer.textContent='Sin cantidades válidas';
            }
            const moneyArea=summary.querySelector('[data-money-available]');
            if(moneyArea)moneyArea.hidden=!totals.hasPrice;
            set('total-subtotal',money(totals.subtotal));
            set('total-igv',money(totals.igv));
            set('total-payable',money(totals.total));
            let hint='';
            if(totals.invalid)hint='Revise cantidades y valores unitarios: deben ser números válidos.';
            else if(totals.hasPrice&&totals.missingPrice)hint='Complete el valor unitario de todos los productos.';
            else if(!totals.hasPrice)hint=isGuide
                ?'Guía de traslado: las cantidades están registradas, sin valorización comercial.'
                :'Ingrese los valores unitarios de los productos.';
            else if(!mode)hint='Seleccione si los precios incluyen IGV para desagregar correctamente el impuesto.';
            else hint=mode==='INCLUIDO'
                ?'El 18 % ya está incluido en los valores unitarios ingresados.'
                :'El 18 % se añade al subtotal sin IGV.';
            set('total-hint',hint);
        }
        function schedule(){
            if(pending)return;
            pending=true;
            if(typeof window.requestAnimationFrame==='function')window.requestAnimationFrame(render);
            else window.setTimeout(render,0);
        }
        body.addEventListener('input',schedule);
        body.addEventListener('change',schedule);
        body.addEventListener('click',schedule);
        modeSelect?.addEventListener('change',schedule);
        new MutationObserver(schedule).observe(body,{childList:true});
        render();
    }

    function init() {
        document.querySelectorAll('form').forEach(initForm);
    }
    const api={calculate,priceCents,quantity,money,init};
    if(typeof window!=='undefined')window.BP_CommercialTotals=api;
    if(typeof module!=='undefined'&&module.exports)module.exports=api;
    if(typeof document!=='undefined'){
        if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});
        else init();
    }
})();
