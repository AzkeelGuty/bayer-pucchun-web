'use strict';
const assert=require('node:assert/strict');
const api=require('../../public/assets/js/commercial-totals.js');
let checks=0;
const ok=(cond,msg)=>{assert.ok(cond,msg);checks++;};
const rows=[
    {cantidad:'4',valor_unitario:'750.00',unidad:'Kilogramo'},
    {cantidad:'5',valor_unitario:'125.00',unidad:'Litro'},
];
const net=api.calculate(rows,'ADICIONAL');
ok(net.subtotal===362500,'Subtotal de cantidades x precio');
ok(net.igv===65250,'IGV 18 %');
ok(net.total===427750,'Total S/ 4277.50');
ok(net.lines[0].importe===300000 && net.lines[1].importe===62500,'Importe por producto');
ok(net.quantities.Kilogramo===4 && net.quantities.Litro===5,'Agrupar por unidad');
const included=api.calculate(rows,'INCLUIDO');
ok(included.subtotal===307203,'Subtotal desagregado cuando IGV incluido');
ok(included.igv===55297,'IGV incluido exacto');
ok(included.total===362500,'No duplicar IGV incluido');
ok(api.money(427750)==='S/ 4,277.50','Moneda formateada');
const noValue=api.calculate([{cantidad:'7',valor_unitario:'',unidad:'Kg'}],null);
ok(!noValue.hasPrice && noValue.total===null,'Guía sin importes conserva cantidades');
ok(noValue.quantities.Kg===7,'Mostrar total de cantidad sin precio');
const conflict=api.calculate([
    {cantidad:'1',valor_unitario:'10',unidad:'Kg'},
    {cantidad:'1',valor_unitario:'',unidad:'Litro'}
],'ADICIONAL');
ok(conflict.hasPrice && conflict.missingPrice,'Detecta precios incompletos');
const invalid=api.calculate([{cantidad:'1.5',valor_unitario:'3.00',unidad:'Kg'}],'INCLUIDO');
ok(invalid.invalid,'No aceptar cantidades fraccionarias');
ok(api.priceCents('7.25')===725,'Parsear centimos sin float');
ok(api.priceCents('7.256')===null,'Rechazar centimos extra');
ok(api.quantity('3.000')===3,'Interpretar cantidad histórica DECIMAL(14,3)');
ok(api.quantity('0')===null,'No aceptar cantidad cero');
console.log('COMMERCIAL TOTALS UI OK ('+checks+' checks)');
