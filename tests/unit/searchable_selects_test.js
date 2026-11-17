'use strict';

const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/assets/js/searchable-selects.js', 'utf8');

let activeDocument;
class FakeClassList {
    constructor(element) { this.el = element; this.set = new Set(); }
    add(...names) { names.forEach(n => this.set.add(n)); }
    remove(...names) { names.forEach(n => this.set.delete(n)); }
    contains(name) { return this.set.has(name); }
    toggle(name, force) {
        if (force === undefined) force = !this.contains(name);
        if (force) this.add(name); else this.remove(name);
        return force;
    }
}
class Element {
    constructor(tag='div') {
        this.tagName=tag.toUpperCase();
        this.dataset={};this.style={};this.attributes={};this.children=[];
        this.listeners={};this.parentNode=null;this.value='';
        this.options=[];this.textContent='';this.classList=new FakeClassList(this);
        this.isConnected=true;this.required=false;
    }
    appendChild(node) {
        if (node.parentNode) node.parentNode.children=node.parentNode.children.filter(x=>x!==node);
        node.parentNode=this;this.children.push(node);return node;
    }
    insertBefore(node, before) {
        const i=this.children.indexOf(before);
        if (i<0) return this.appendChild(node);
        if (node.parentNode) node.parentNode.children=node.parentNode.children.filter(x=>x!==node);
        node.parentNode=this;this.children.splice(i,0,node);return node;
    }
    remove() {if(this.parentNode) this.parentNode.children=this.parentNode.children.filter(x=>x!==this);this.isConnected=false;}
    replaceChildren(...nodes){this.children=[];nodes.forEach(node=>this.appendChild(node));}
    set className(value){this._className=value;this.classList.set=new Set(String(value).split(/\s+/).filter(Boolean));}
    get className(){return this._className||'';}
    set innerHTML(value){this.children=[];this._html=value;}
    get innerHTML(){return this._html||'';}
    contains(other){
        let item=other;
        while(item){if(item===this)return true;item=item.parentNode;}
        return false;
    }
    closest(selector){
        let node=this;
        while(node){
            if(selector==='form' && node.tagName==='FORM') return node;
            node=node.parentNode;
        }
        return null;
    }
    setAttribute(key,value){this.attributes[key]=String(value);}
    removeAttribute(key){delete this.attributes[key];if(key==='data-search-enhanced')delete this.dataset.searchEnhanced;}
    getAttribute(key){return this.attributes[key]??null;}
    addEventListener(type,fn){(this.listeners[type]??=[]).push(fn);}
    dispatch(type,detail={}){const event={type,target:this,key:detail.key||'',preventDefault(){},...detail};for(const fn of this.listeners[type]||[])fn(event);return event;}
    dispatchEvent(event){this.dispatch(event.type,event);}
    get selectedOptions(){return this.options.filter(o=>o.value===this.value);}
    getBoundingClientRect(){return {left:30,top:80,bottom:122,width:370,right:400};}
    focus(){
        activeDocument.activeElement=this;
        activeDocument.fire('focusin',{target:this});
        this.dispatch('focus');
    }
    select(){}
    setCustomValidity(message){this.validationMessage=message;}
    querySelectorAll(selector){
        if(selector==='.incremental-select-option')return this.children.filter(c=>c.classList.contains('incremental-select-option'));
        return [];
    }
}
const handlers={};
activeDocument={
    readyState:'loading',activeElement:null,
    body:new Element('body'),
    createElement(tag){return new Element(tag);},
    addEventListener(type,fn){(handlers[type]??=[]).push(fn);},
    fire(type,event){for(const fn of handlers[type]||[])fn(event);},
    querySelectorAll(selector) {
        if(selector==='.incremental-select-menu')return this.body.children.filter(x=>x.classList.contains('incremental-select-menu'));
        return [];
    },
};
const browser={
    innerHeight:800,innerWidth:1300,
    location:{href:'http://localhost/erp/'},
    setTimeout,clearTimeout,addEventListener(){},
};
const ctx={document:activeDocument,window:browser,console,URL,AbortController,clearTimeout,setTimeout,Event:class{constructor(type){this.type=type;}}};
vm.runInNewContext(source,ctx,{filename:'searchable-selects.js'});
const api=browser.BP_SearchableSelects;
assert.ok(api?.enhance);

function createSelect(code,name) {
    const parent=new Element();
    const select=new Element('select');
    select.dataset.searchMin='1';
    select.dataset.searchPlaceholder='Escribe el inicio de '+name+'...';
    select.options=[
        {value:'',textContent:'Seleccionar...',dataset:{}},
        {value:code,textContent:code+' · '+name,dataset:{}},
    ];
    parent.appendChild(select);
    api.enhance(select);
    const widget=parent.children[0];
    return {select,input:widget.children[1].children[0],menu:activeDocument.body.children.at(-1)};
}

let checks=0;
const check=(condition,label)=>{assert.ok(condition,label);checks++;};
const cliente=createSelect('CLI-100','Cliente');
const vendedor=createSelect('VEN-200','Vendedor');
const producto=createSelect('PRO-300','Producto');

cliente.input.focus();
check(!cliente.menu.classList.contains('open'),'Cliente vacío no abre panel');
check(cliente.menu.children.length===0,'Cliente vacío no muestra Escribe para buscar');
cliente.input.value='CLI';
cliente.input.dispatch('input');
check(cliente.menu.classList.contains('open'),'Cliente con texto muestra sugerencia');
check(cliente.menu.children.length===1,'Solo una sugerencia visible');

vendedor.input.focus();
check(!cliente.menu.classList.contains('open'),'Al enfocar Vendedor se cierra Cliente');
check(!vendedor.menu.classList.contains('open'),'Vendedor vacío no abre panel');

vendedor.input.value='VEN';
vendedor.input.dispatch('input');
check(vendedor.menu.classList.contains('open'),'Vendedor con texto abre lista');
producto.input.focus();
check(!vendedor.menu.classList.contains('open'),'Al enfocar Producto se cierra Vendedor');
check(!producto.menu.classList.contains('open'),'Producto vacío no abre panel');

producto.input.value='PRO';
producto.input.dispatch('input');
check(producto.menu.classList.contains('open'),'Producto con texto abre lista');
producto.input.dispatch('keydown',{key:'Escape'});
check(!producto.menu.classList.contains('open'),'Escape cierra menú');

cliente.input.focus();
cliente.input.value='CLI';
cliente.input.dispatch('input');
cliente.menu.children[0].dispatch('click');
check(cliente.select.value==='CLI-100','La selección conserva ID real del catálogo');
check(!cliente.menu.classList.contains('open'),'Después de seleccionar se cierra menú');
cliente.input.focus();
check(!cliente.menu.classList.contains('open'),'El campo seleccionado no reabre menú al enfocar');
cliente.input.value='CLI';
cliente.input.dispatch('input');
check(cliente.input.value==='CLI','Editar una selección no borra el nuevo texto escrito');
check(cliente.select.value==='','Escribir una selección diferente limpia el valor anterior');
check(cliente.menu.classList.contains('open'),'Tras editar una selección vuelven a aparecer resultados');

check(activeDocument.querySelectorAll('.incremental-select-menu').length===3,'Tres campos crean tres menús controlados');
api.reset();
check(activeDocument.querySelectorAll('.incremental-select-menu').length===0,'La navegación limpia los menús flotantes');
check(!cliente.select.dataset.searchEnhanced,'Permite re-inicializar tras cambio de página');
check(source.includes('widget.requestNonce') && source.includes('widget.controller?.abort()'),
    'Las respuestas de búsquedas remotas antiguas se invalidan');

console.log('SEARCHABLE SELECTS UI OK ('+checks+' checks)');
