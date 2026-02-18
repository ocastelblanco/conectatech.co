#!/usr/bin/env node
/**
 * check-best-practices.js
 * 
 * Verifica un archivo específico contra las reglas de angular-best-practices.
 * 
 * Uso:
 *   node check-best-practices.js <ruta-archivo>
 *   node check-best-practices.js src/app/components/user/user.component.ts
 * 
 * Este script es una plantilla. Adapta las reglas según tu proyecto.
 */

const fs = require('fs');
const path = require('path');

// Colores para output
const colors = {
  red: '\x1b[31m',
  yellow: '\x1b[33m',
  green: '\x1b[32m',
  blue: '\x1b[34m',
  reset: '\x1b[0m',
  bold: '\x1b[1m'
};

// Obtener archivo de argumentos
const filePath = process.argv[2];

if (!filePath) {
  console.error(`${colors.red}Error: Debes especificar un archivo${colors.reset}`);
  console.log('Uso: node check-best-practices.js <ruta-archivo>');
  process.exit(1);
}

if (!fs.existsSync(filePath)) {
  console.error(`${colors.red}Error: Archivo no encontrado: ${filePath}${colors.reset}`);
  process.exit(1);
}

// Determinar tipo de archivo
const ext = path.extname(filePath);
const content = fs.readFileSync(filePath, 'utf8');
const issues = [];

/**
 * Reglas para archivos TypeScript (.ts)
 */
function checkTypeScript(content, filePath) {
  const lines = content.split('\n');
  
  // Verificar si es un componente
  const isComponent = content.includes('@Component');
  const isService = content.includes('@Injectable');
  const isGuard = filePath.includes('.guard.');
  
  if (isComponent) {
    checkComponentRules(content, lines);
  }
  
  if (isService) {
    checkServiceRules(content, lines);
  }
  
  // Reglas generales para TypeScript
  checkGeneralTypeScriptRules(content, lines);
}

function checkComponentRules(content, lines) {
  // 001: Standalone components
  if (content.includes('@Component') && !content.includes('standalone: true') && !content.includes('standalone:true')) {
    issues.push({
      rule: '001-standalone-components',
      severity: 'critical',
      message: 'Componente debe tener standalone: true',
      suggestion: 'Agregar standalone: true al decorador @Component'
    });
  }
  
  // 006: OnPush change detection
  if (content.includes('@Component') && !content.includes('ChangeDetectionStrategy.OnPush')) {
    issues.push({
      rule: '006-change-detection-onpush',
      severity: 'critical',
      message: 'Componente debe usar ChangeDetectionStrategy.OnPush',
      suggestion: 'Agregar changeDetection: ChangeDetectionStrategy.OnPush'
    });
  }
  
  // 002: Signals pattern - verificar decoradores legacy
  if (content.includes('@Input()') || content.includes('@Input(')) {
    issues.push({
      rule: '002-signals-pattern',
      severity: 'critical',
      message: 'Usar input() en lugar de @Input()',
      suggestion: 'Reemplazar @Input() por input() o input.required()'
    });
  }
  
  if (content.includes('@Output()') || content.includes('@Output(')) {
    issues.push({
      rule: '002-signals-pattern',
      severity: 'critical',
      message: 'Usar output() en lugar de @Output()',
      suggestion: 'Reemplazar @Output() por output()'
    });
  }
  
  // 003: Inyección de dependencias
  if (content.includes('constructor(') && content.includes('private ') && content.includes(': ')) {
    const constructorMatch = content.match(/constructor\s*\([^)]+\)/);
    if (constructorMatch && constructorMatch[0].includes('private')) {
      issues.push({
        rule: '003-component-design',
        severity: 'warning',
        message: 'Preferir inject() sobre inyección en constructor',
        suggestion: 'Usar inject(ServiceName) en lugar de constructor injection'
      });
    }
  }
}

function checkServiceRules(content, lines) {
  // Verificar providedIn
  if (content.includes('@Injectable') && !content.includes("providedIn: 'root'") && !content.includes('providedIn: root')) {
    issues.push({
      rule: '003-component-design',
      severity: 'warning',
      message: 'Servicio debe especificar providedIn',
      suggestion: "Agregar providedIn: 'root' si es singleton, o especificar scope"
    });
  }
}

function checkGeneralTypeScriptRules(content, lines) {
  // Verificar imports de CommonModule
  if (content.includes('CommonModule')) {
    issues.push({
      rule: '008-optimized-bundles',
      severity: 'warning',
      message: 'Evitar importar CommonModule completo',
      suggestion: 'Importar directivas/pipes específicos: NgIf, NgFor, DatePipe, etc.'
    });
  }
  
  // Verificar uso de bypassSecurityTrust
  if (content.includes('bypassSecurityTrust')) {
    issues.push({
      rule: '012-sanitization-requirements',
      severity: 'critical',
      message: 'Uso de bypassSecurityTrust* detectado',
      suggestion: 'Verificar que el contenido esté validado antes de bypass'
    });
  }
  
  // Verificar NgZone APIs incompatibles con zoneless
  const zonelessIncompatible = ['onMicrotaskEmpty', 'onStable', 'onUnstable', 'isStable'];
  zonelessIncompatible.forEach(api => {
    if (content.includes(`NgZone.${api}`) || content.includes(`.${api}`)) {
      issues.push({
        rule: '009-zoneless-angular',
        severity: 'warning',
        message: `NgZone.${api} no es compatible con zoneless`,
        suggestion: 'Usar afterNextRender() o afterEveryRender() en su lugar'
      });
    }
  });
}

/**
 * Reglas para archivos HTML (.html)
 */
function checkHTML(content, filePath) {
  // 005: Control flow moderno
  if (content.includes('*ngIf')) {
    issues.push({
      rule: '005-modern-control-flow',
      severity: 'critical',
      message: 'Usar @if en lugar de *ngIf',
      suggestion: 'Migrar con: ng generate @angular/core:control-flow'
    });
  }
  
  if (content.includes('*ngFor')) {
    issues.push({
      rule: '005-modern-control-flow',
      severity: 'critical',
      message: 'Usar @for en lugar de *ngFor',
      suggestion: 'Reemplazar por @for (item of items; track item.id)'
    });
  }
  
  if (content.includes('*ngSwitch') || content.includes('*ngSwitchCase')) {
    issues.push({
      rule: '005-modern-control-flow',
      severity: 'critical',
      message: 'Usar @switch/@case en lugar de *ngSwitch',
      suggestion: 'Reemplazar por @switch/@case/@default'
    });
  }
  
  // 008: ngClass/ngStyle
  if (content.includes('[ngClass]')) {
    issues.push({
      rule: '008-optimized-bundles',
      severity: 'warning',
      message: 'Preferir [class] sobre [ngClass]',
      suggestion: 'Usar [class.nombre]="condicion" o [class]="objeto"'
    });
  }
  
  if (content.includes('[ngStyle]')) {
    issues.push({
      rule: '008-optimized-bundles',
      severity: 'warning',
      message: 'Preferir [style] sobre [ngStyle]',
      suggestion: 'Usar [style.propiedad]="valor" o [style]="objeto"'
    });
  }
  
  // 015: ARIA - imágenes sin alt
  const imgWithoutAlt = content.match(/<img(?![^>]*alt=)[^>]*>/gi);
  if (imgWithoutAlt) {
    issues.push({
      rule: '015-aria-requirements',
      severity: 'critical',
      message: `${imgWithoutAlt.length} imagen(es) sin atributo alt`,
      suggestion: 'Agregar alt descriptivo o alt="" para imágenes decorativas'
    });
  }
  
  // 015: Botones sin texto accesible
  const buttonsWithOnlyIcon = content.match(/<button[^>]*>(\s*<(svg|i|mat-icon)[^>]*>.*?<\/(svg|i|mat-icon)>\s*)<\/button>/gi);
  if (buttonsWithOnlyIcon) {
    issues.push({
      rule: '015-aria-requirements',
      severity: 'warning',
      message: 'Botón con solo icono sin aria-label',
      suggestion: 'Agregar aria-label al botón para accesibilidad'
    });
  }
  
  // 010: Memoization - llamadas a funciones en template
  const functionCalls = content.match(/\{\{\s*\w+\([^)]*\)\s*\}\}/g);
  if (functionCalls) {
    issues.push({
      rule: '010-memoization-patterns',
      severity: 'warning',
      message: `${functionCalls.length} llamada(s) a función en template`,
      suggestion: 'Usar computed() signals en lugar de métodos en templates'
    });
  }
}

/**
 * Reglas para archivos SCSS (.scss)
 */
function checkSCSS(content, filePath) {
  // Verificar !important
  const importantCount = (content.match(/!important/g) || []).length;
  if (importantCount > 0) {
    issues.push({
      rule: '008-optimized-bundles',
      severity: 'warning',
      message: `${importantCount} uso(s) de !important detectado(s)`,
      suggestion: 'Evitar !important, usar especificidad CSS apropiada'
    });
  }
}

// Ejecutar verificación según tipo de archivo
console.log(`\n${colors.blue}${colors.bold}Verificando: ${filePath}${colors.reset}\n`);

switch (ext) {
  case '.ts':
    checkTypeScript(content, filePath);
    break;
  case '.html':
    checkHTML(content, filePath);
    break;
  case '.scss':
  case '.css':
    checkSCSS(content, filePath);
    break;
  default:
    console.log(`${colors.yellow}Tipo de archivo no soportado: ${ext}${colors.reset}`);
    process.exit(0);
}

// Mostrar resultados
if (issues.length === 0) {
  console.log(`${colors.green}✓ No se encontraron problemas${colors.reset}\n`);
  process.exit(0);
}

// Agrupar por severidad
const critical = issues.filter(i => i.severity === 'critical');
const warnings = issues.filter(i => i.severity === 'warning');

if (critical.length > 0) {
  console.log(`${colors.red}${colors.bold}CRÍTICOS (${critical.length}):${colors.reset}`);
  critical.forEach(issue => {
    console.log(`  ${colors.red}✗${colors.reset} [${issue.rule}] ${issue.message}`);
    console.log(`    ${colors.blue}→ ${issue.suggestion}${colors.reset}`);
  });
  console.log();
}

if (warnings.length > 0) {
  console.log(`${colors.yellow}${colors.bold}ADVERTENCIAS (${warnings.length}):${colors.reset}`);
  warnings.forEach(issue => {
    console.log(`  ${colors.yellow}⚠${colors.reset} [${issue.rule}] ${issue.message}`);
    console.log(`    ${colors.blue}→ ${issue.suggestion}${colors.reset}`);
  });
  console.log();
}

// Resumen
console.log(`${colors.bold}Resumen:${colors.reset} ${critical.length} críticos, ${warnings.length} advertencias\n`);

// Exit code basado en errores críticos
process.exit(critical.length > 0 ? 1 : 0);
