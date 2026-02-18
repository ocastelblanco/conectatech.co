#!/usr/bin/env node
/**
 * generate-compliance-report.js
 * 
 * Genera un reporte completo de cumplimiento de las reglas de angular-best-practices.
 * 
 * Uso:
 *   node generate-compliance-report.js [directorio] [--json] [--html]
 *   node generate-compliance-report.js src/app --json > report.json
 *   node generate-compliance-report.js src/app --html > report.html
 * 
 * Este script es una plantilla. Adapta según las necesidades de tu proyecto.
 */

const fs = require('fs');
const path = require('path');

// Colores para output
const colors = {
  red: '\x1b[31m',
  yellow: '\x1b[33m',
  green: '\x1b[32m',
  blue: '\x1b[34m',
  cyan: '\x1b[36m',
  reset: '\x1b[0m',
  bold: '\x1b[1m'
};

// Configuración
const args = process.argv.slice(2);
const outputJson = args.includes('--json');
const outputHtml = args.includes('--html');
const targetDir = args.find(arg => !arg.startsWith('--')) || 'src/app';

// Extensiones a analizar
const extensions = ['.ts', '.html', '.scss'];

// Directorios a ignorar
const ignoreDirs = ['node_modules', 'dist', '.angular', 'coverage', '.git'];

// Estructura del reporte
const report = {
  timestamp: new Date().toISOString(),
  directory: targetDir,
  summary: {
    totalFiles: 0,
    filesWithIssues: 0,
    critical: 0,
    warnings: 0,
    suggestions: 0
  },
  byRule: {},
  byFile: {},
  compliance: {}
};

// Reglas y sus categorías
const rules = {
  '001-standalone-components': { category: 'architecture', severity: 'critical' },
  '002-signals-pattern': { category: 'architecture', severity: 'critical' },
  '003-component-design': { category: 'architecture', severity: 'warning' },
  '005-modern-control-flow': { category: 'architecture', severity: 'critical' },
  '006-change-detection-onpush': { category: 'performance', severity: 'critical' },
  '008-optimized-bundles': { category: 'performance', severity: 'warning' },
  '009-zoneless-angular': { category: 'performance', severity: 'warning' },
  '010-memoization-patterns': { category: 'performance', severity: 'warning' },
  '012-sanitization-requirements': { category: 'security', severity: 'critical' },
  '015-aria-requirements': { category: 'accessibility', severity: 'critical' },
  '016-keyboard-navigation': { category: 'accessibility', severity: 'critical' }
};

/**
 * Buscar archivos recursivamente
 */
function findFiles(dir, files = []) {
  if (!fs.existsSync(dir)) {
    console.error(`${colors.red}Error: Directorio no encontrado: ${dir}${colors.reset}`);
    process.exit(1);
  }

  const items = fs.readdirSync(dir);
  
  for (const item of items) {
    const fullPath = path.join(dir, item);
    const stat = fs.statSync(fullPath);
    
    if (stat.isDirectory()) {
      if (!ignoreDirs.includes(item)) {
        findFiles(fullPath, files);
      }
    } else if (stat.isFile()) {
      const ext = path.extname(item);
      if (extensions.includes(ext)) {
        files.push(fullPath);
      }
    }
  }
  
  return files;
}

/**
 * Analizar archivo y retornar issues
 */
function analyzeFile(filePath) {
  const content = fs.readFileSync(filePath, 'utf8');
  const ext = path.extname(filePath);
  const issues = [];
  
  if (ext === '.ts') {
    // Standalone components
    if (content.includes('@Component') && !content.includes('standalone: true')) {
      issues.push({ rule: '001-standalone-components', severity: 'critical' });
    }
    
    // OnPush
    if (content.includes('@Component') && !content.includes('ChangeDetectionStrategy.OnPush')) {
      issues.push({ rule: '006-change-detection-onpush', severity: 'critical' });
    }
    
    // Signals
    if (content.includes('@Input()') || content.includes('@Output()')) {
      issues.push({ rule: '002-signals-pattern', severity: 'critical' });
    }
    
    // Constructor injection
    if (content.includes('constructor(') && /constructor\s*\([^)]*private\s+\w+/.test(content)) {
      issues.push({ rule: '003-component-design', severity: 'warning' });
    }
    
    // CommonModule
    if (content.includes('CommonModule')) {
      issues.push({ rule: '008-optimized-bundles', severity: 'warning' });
    }
    
    // bypassSecurityTrust
    if (content.includes('bypassSecurityTrust')) {
      issues.push({ rule: '012-sanitization-requirements', severity: 'critical' });
    }
    
    // NgZone incompatible APIs
    if (/NgZone\.(onStable|onMicrotaskEmpty|onUnstable)/.test(content)) {
      issues.push({ rule: '009-zoneless-angular', severity: 'warning' });
    }
  }
  
  if (ext === '.html') {
    // Legacy directives
    if (content.includes('*ngIf') || content.includes('*ngFor') || content.includes('*ngSwitch')) {
      issues.push({ rule: '005-modern-control-flow', severity: 'critical' });
    }
    
    // ngClass/ngStyle
    if (content.includes('[ngClass]') || content.includes('[ngStyle]')) {
      issues.push({ rule: '008-optimized-bundles', severity: 'warning' });
    }
    
    // Images without alt
    if (/<img(?![^>]*alt=)[^>]*>/i.test(content)) {
      issues.push({ rule: '015-aria-requirements', severity: 'critical' });
    }
    
    // Function calls in templates
    if (/\{\{\s*\w+\([^)]*\)\s*\}\}/.test(content)) {
      issues.push({ rule: '010-memoization-patterns', severity: 'warning' });
    }
  }
  
  return issues;
}

/**
 * Generar reporte
 */
function generateReport() {
  const files = findFiles(targetDir);
  report.summary.totalFiles = files.length;
  
  // Inicializar contadores por regla
  Object.keys(rules).forEach(rule => {
    report.byRule[rule] = {
      count: 0,
      files: []
    };
  });
  
  // Analizar cada archivo
  files.forEach(file => {
    const issues = analyzeFile(file);
    const relPath = path.relative(process.cwd(), file);
    
    if (issues.length > 0) {
      report.summary.filesWithIssues++;
      report.byFile[relPath] = issues;
      
      issues.forEach(issue => {
        if (issue.severity === 'critical') report.summary.critical++;
        else if (issue.severity === 'warning') report.summary.warnings++;
        else report.summary.suggestions++;
        
        if (report.byRule[issue.rule]) {
          report.byRule[issue.rule].count++;
          if (!report.byRule[issue.rule].files.includes(relPath)) {
            report.byRule[issue.rule].files.push(relPath);
          }
        }
      });
    }
  });
  
  // Calcular compliance por categoría
  const categories = ['architecture', 'performance', 'security', 'accessibility'];
  categories.forEach(cat => {
    const catRules = Object.entries(rules).filter(([_, r]) => r.category === cat);
    const totalIssues = catRules.reduce((sum, [rule, _]) => sum + report.byRule[rule].count, 0);
    const maxPossible = catRules.length * report.summary.totalFiles;
    report.compliance[cat] = maxPossible > 0 
      ? Math.round((1 - totalIssues / maxPossible) * 100) 
      : 100;
  });
  
  // Compliance general
  const totalIssues = report.summary.critical + report.summary.warnings;
  const maxPossible = Object.keys(rules).length * report.summary.totalFiles;
  report.compliance.overall = maxPossible > 0
    ? Math.round((1 - totalIssues / maxPossible) * 100)
    : 100;
}

/**
 * Output en formato JSON
 */
function outputAsJson() {
  console.log(JSON.stringify(report, null, 2));
}

/**
 * Output en formato HTML
 */
function outputAsHtml() {
  const html = `<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Reporte de Cumplimiento - Angular Best Practices</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; }
    h1 { color: #1976d2; }
    .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; margin: 20px 0; }
    .card { background: #f5f5f5; padding: 20px; border-radius: 8px; text-align: center; }
    .card.critical { background: #ffebee; }
    .card.warning { background: #fff3e0; }
    .card.success { background: #e8f5e9; }
    .card h3 { margin: 0 0 10px; }
    .card .number { font-size: 2em; font-weight: bold; }
    .compliance { margin: 30px 0; }
    .bar { background: #e0e0e0; border-radius: 4px; height: 24px; overflow: hidden; }
    .bar-fill { height: 100%; transition: width 0.3s; }
    .bar-fill.good { background: #4caf50; }
    .bar-fill.medium { background: #ff9800; }
    .bar-fill.bad { background: #f44336; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e0e0e0; }
    th { background: #f5f5f5; }
    .severity-critical { color: #d32f2f; }
    .severity-warning { color: #f57c00; }
  </style>
</head>
<body>
  <h1>🔍 Reporte de Cumplimiento - Angular Best Practices</h1>
  <p>Generado: ${report.timestamp}</p>
  <p>Directorio: <code>${report.directory}</code></p>
  
  <div class="summary">
    <div class="card"><h3>Archivos</h3><div class="number">${report.summary.totalFiles}</div></div>
    <div class="card ${report.summary.critical > 0 ? 'critical' : 'success'}"><h3>Críticos</h3><div class="number">${report.summary.critical}</div></div>
    <div class="card ${report.summary.warnings > 0 ? 'warning' : 'success'}"><h3>Advertencias</h3><div class="number">${report.summary.warnings}</div></div>
    <div class="card success"><h3>Cumplimiento</h3><div class="number">${report.compliance.overall}%</div></div>
  </div>
  
  <h2>📊 Cumplimiento por Categoría</h2>
  <div class="compliance">
    ${Object.entries(report.compliance).filter(([k]) => k !== 'overall').map(([cat, pct]) => `
    <div style="margin: 10px 0;">
      <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
        <span>${cat.charAt(0).toUpperCase() + cat.slice(1)}</span>
        <span>${pct}%</span>
      </div>
      <div class="bar">
        <div class="bar-fill ${pct >= 80 ? 'good' : pct >= 50 ? 'medium' : 'bad'}" style="width: ${pct}%"></div>
      </div>
    </div>
    `).join('')}
  </div>
  
  <h2>📋 Issues por Regla</h2>
  <table>
    <thead><tr><th>Regla</th><th>Categoría</th><th>Count</th></tr></thead>
    <tbody>
      ${Object.entries(report.byRule)
        .filter(([_, data]) => data.count > 0)
        .sort((a, b) => b[1].count - a[1].count)
        .map(([rule, data]) => `
        <tr>
          <td class="severity-${rules[rule]?.severity || 'warning'}">${rule}</td>
          <td>${rules[rule]?.category || 'other'}</td>
          <td>${data.count}</td>
        </tr>
      `).join('')}
    </tbody>
  </table>
  
  <h2>📁 Archivos con Issues</h2>
  <table>
    <thead><tr><th>Archivo</th><th>Issues</th></tr></thead>
    <tbody>
      ${Object.entries(report.byFile).map(([file, issues]) => `
        <tr>
          <td><code>${file}</code></td>
          <td>${issues.map(i => `<span class="severity-${i.severity}">${i.rule}</span>`).join(', ')}</td>
        </tr>
      `).join('')}
    </tbody>
  </table>
</body>
</html>`;
  console.log(html);
}

/**
 * Output en formato texto (terminal)
 */
function outputAsText() {
  console.log(`\n${colors.blue}${colors.bold}═══════════════════════════════════════════════════════════${colors.reset}`);
  console.log(`${colors.blue}${colors.bold}   REPORTE DE CUMPLIMIENTO - Angular Best Practices${colors.reset}`);
  console.log(`${colors.blue}${colors.bold}═══════════════════════════════════════════════════════════${colors.reset}\n`);
  
  console.log(`${colors.cyan}Directorio:${colors.reset} ${report.directory}`);
  console.log(`${colors.cyan}Fecha:${colors.reset} ${report.timestamp}\n`);
  
  // Resumen
  console.log(`${colors.bold}RESUMEN${colors.reset}`);
  console.log(`  Archivos analizados: ${report.summary.totalFiles}`);
  console.log(`  Archivos con issues: ${report.summary.filesWithIssues}`);
  console.log(`  ${colors.red}Críticos: ${report.summary.critical}${colors.reset}`);
  console.log(`  ${colors.yellow}Advertencias: ${report.summary.warnings}${colors.reset}`);
  console.log();
  
  // Compliance
  console.log(`${colors.bold}CUMPLIMIENTO POR CATEGORÍA${colors.reset}`);
  Object.entries(report.compliance).forEach(([cat, pct]) => {
    const bar = '█'.repeat(Math.round(pct / 5)) + '░'.repeat(20 - Math.round(pct / 5));
    const color = pct >= 80 ? colors.green : pct >= 50 ? colors.yellow : colors.red;
    console.log(`  ${cat.padEnd(15)} ${color}${bar}${colors.reset} ${pct}%`);
  });
  console.log();
  
  // Top issues
  const topIssues = Object.entries(report.byRule)
    .filter(([_, data]) => data.count > 0)
    .sort((a, b) => b[1].count - a[1].count)
    .slice(0, 10);
  
  if (topIssues.length > 0) {
    console.log(`${colors.bold}TOP ISSUES${colors.reset}`);
    topIssues.forEach(([rule, data]) => {
      const severity = rules[rule]?.severity || 'warning';
      const color = severity === 'critical' ? colors.red : colors.yellow;
      console.log(`  ${color}${rule}${colors.reset}: ${data.count} ocurrencias`);
    });
  }
  
  console.log(`\n${colors.blue}═══════════════════════════════════════════════════════════${colors.reset}\n`);
}

// Ejecutar
generateReport();

if (outputJson) {
  outputAsJson();
} else if (outputHtml) {
  outputAsHtml();
} else {
  outputAsText();
}

// Exit code
process.exit(report.summary.critical > 0 ? 1 : 0);
