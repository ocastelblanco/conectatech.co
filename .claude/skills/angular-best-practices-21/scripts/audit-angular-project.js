#!/usr/bin/env node
/**
 * Script de Auditoría de Mejores Prácticas Angular
 * 
 * Uso:
 *   node audit-angular-project.js [ruta-proyecto]
 * 
 * Ejemplo:
 *   node audit-angular-project.js ./my-angular-app
 * 
 * Este es un script plantilla. Adaptar según las necesidades del proyecto.
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// ============================================
// CONFIGURACIÓN
// ============================================

const CONFIG = {
  // Extensiones a analizar
  extensions: ['.ts', '.html', '.scss'],
  
  // Directorios a ignorar
  ignoreDirs: ['node_modules', 'dist', '.angular', 'coverage'],
  
  // Umbrales
  thresholds: {
    coverageMinimum: 80,
    bundleSizeWarning: 500 * 1024,  // 500KB
    bundleSizeError: 1024 * 1024,    // 1MB
  }
};

// ============================================
// REGLAS DE AUDITORÍA
// ============================================

const RULES = {
  // Arquitectura
  'standalone-components': {
    severity: 'critical',
    pattern: /@Component\(\{[^}]*(?<!standalone:\s*true)[^}]*\}\)/s,
    antiPattern: true,
    message: 'Componente sin standalone: true'
  },
  
  'signals-input': {
    severity: 'critical',
    pattern: /@Input\(\)/g,
    antiPattern: true,
    message: 'Usar input() en lugar de @Input()'
  },
  
  'signals-output': {
    severity: 'critical',
    pattern: /@Output\(\)/g,
    antiPattern: true,
    message: 'Usar output() en lugar de @Output()'
  },
  
  'inject-function': {
    severity: 'warning',
    pattern: /constructor\([^)]*private\s+\w+:\s+\w+/g,
    antiPattern: true,
    message: 'Usar inject() en lugar de inyección por constructor'
  },
  
  // Rendimiento
  'change-detection-onpush': {
    severity: 'critical',
    pattern: /changeDetection:\s*ChangeDetectionStrategy\.OnPush/,
    antiPattern: false,
    missingMessage: 'Falta ChangeDetectionStrategy.OnPush'
  },
  
  'common-module-import': {
    severity: 'warning',
    pattern: /imports:\s*\[[^\]]*CommonModule/g,
    antiPattern: true,
    message: 'Importar pipes/directivas específicas en lugar de CommonModule'
  },
  
  // Seguridad
  'bypass-security': {
    severity: 'critical',
    pattern: /bypassSecurityTrust(Html|Style|Url|ResourceUrl|Script)/g,
    antiPattern: true,
    message: 'Uso de bypassSecurityTrust* requiere revisión de seguridad'
  },
  
  'inner-html-binding': {
    severity: 'warning',
    pattern: /\[innerHTML\]/g,
    antiPattern: true,
    message: 'innerHTML binding - verificar que el contenido esté sanitizado'
  }
};

// ============================================
// FUNCIONES DE ANÁLISIS
// ============================================

function getAllFiles(dirPath, arrayOfFiles = []) {
  const files = fs.readdirSync(dirPath);

  files.forEach(file => {
    const fullPath = path.join(dirPath, file);
    
    if (CONFIG.ignoreDirs.includes(file)) return;
    
    if (fs.statSync(fullPath).isDirectory()) {
      getAllFiles(fullPath, arrayOfFiles);
    } else {
      const ext = path.extname(file);
      if (CONFIG.extensions.includes(ext)) {
        arrayOfFiles.push(fullPath);
      }
    }
  });

  return arrayOfFiles;
}

function analyzeFile(filePath) {
  const content = fs.readFileSync(filePath, 'utf8');
  const issues = [];
  const isComponent = content.includes('@Component');
  
  for (const [ruleId, rule] of Object.entries(RULES)) {
    // Solo aplicar reglas de componente a archivos de componente
    if (ruleId.includes('component') || ruleId.includes('change-detection')) {
      if (!isComponent) continue;
    }
    
    if (rule.antiPattern) {
      // Buscar patrón que NO debería existir
      const matches = content.match(rule.pattern);
      if (matches) {
        issues.push({
          rule: ruleId,
          severity: rule.severity,
          message: rule.message,
          count: matches.length,
          file: filePath
        });
      }
    } else {
      // Buscar patrón que DEBERÍA existir
      if (isComponent && !rule.pattern.test(content)) {
        issues.push({
          rule: ruleId,
          severity: rule.severity,
          message: rule.missingMessage,
          count: 1,
          file: filePath
        });
      }
    }
  }
  
  return issues;
}

function checkAngularJson(projectPath) {
  const issues = [];
  const angularJsonPath = path.join(projectPath, 'angular.json');
  
  if (!fs.existsSync(angularJsonPath)) {
    issues.push({
      rule: 'angular-json',
      severity: 'critical',
      message: 'No se encontró angular.json',
      file: projectPath
    });
    return issues;
  }
  
  const angularJson = JSON.parse(fs.readFileSync(angularJsonPath, 'utf8'));
  const projects = angularJson.projects || {};
  
  for (const [projectName, project] of Object.entries(projects)) {
    const buildConfig = project.architect?.build?.configurations?.production;
    
    if (!buildConfig?.budgets) {
      issues.push({
        rule: 'bundle-budgets',
        severity: 'warning',
        message: `Proyecto ${projectName}: No hay budgets configurados`,
        file: angularJsonPath
      });
    }
  }
  
  return issues;
}

// ============================================
// GENERACIÓN DE REPORTE
// ============================================

function generateReport(issues) {
  const report = {
    timestamp: new Date().toISOString(),
    summary: {
      total: issues.length,
      critical: issues.filter(i => i.severity === 'critical').length,
      warning: issues.filter(i => i.severity === 'warning').length,
      suggestion: issues.filter(i => i.severity === 'suggestion').length
    },
    byRule: {},
    issues: issues
  };
  
  // Agrupar por regla
  for (const issue of issues) {
    if (!report.byRule[issue.rule]) {
      report.byRule[issue.rule] = [];
    }
    report.byRule[issue.rule].push(issue);
  }
  
  return report;
}

function printReport(report) {
  console.log('\n========================================');
  console.log('  REPORTE DE AUDITORÍA ANGULAR');
  console.log('========================================\n');
  
  console.log(`📅 Fecha: ${report.timestamp}`);
  console.log(`📊 Total de issues: ${report.summary.total}`);
  console.log(`   🔴 Critical: ${report.summary.critical}`);
  console.log(`   🟡 Warning: ${report.summary.warning}`);
  console.log(`   🔵 Suggestion: ${report.summary.suggestion}`);
  
  console.log('\n--- ISSUES POR REGLA ---\n');
  
  for (const [rule, ruleIssues] of Object.entries(report.byRule)) {
    const severity = ruleIssues[0].severity;
    const emoji = severity === 'critical' ? '🔴' : severity === 'warning' ? '🟡' : '🔵';
    
    console.log(`${emoji} ${rule} (${ruleIssues.length} ocurrencias)`);
    
    for (const issue of ruleIssues.slice(0, 5)) {
      console.log(`   └─ ${issue.file}`);
      console.log(`      ${issue.message}`);
    }
    
    if (ruleIssues.length > 5) {
      console.log(`   └─ ... y ${ruleIssues.length - 5} más`);
    }
    console.log();
  }
  
  // Exit code basado en issues críticos
  if (report.summary.critical > 0) {
    console.log('❌ FALLÓ: Se encontraron issues críticos\n');
    process.exit(1);
  } else if (report.summary.warning > 0) {
    console.log('⚠️  ADVERTENCIA: Se encontraron issues de warning\n');
    process.exit(0);
  } else {
    console.log('✅ PASÓ: No se encontraron issues\n');
    process.exit(0);
  }
}

// ============================================
// MAIN
// ============================================

function main() {
  const projectPath = process.argv[2] || '.';
  
  if (!fs.existsSync(projectPath)) {
    console.error(`Error: No existe el directorio ${projectPath}`);
    process.exit(1);
  }
  
  console.log(`\n🔍 Analizando proyecto en: ${path.resolve(projectPath)}`);
  
  // Recolectar todos los archivos
  const srcPath = path.join(projectPath, 'src');
  if (!fs.existsSync(srcPath)) {
    console.error('Error: No se encontró directorio src/');
    process.exit(1);
  }
  
  const files = getAllFiles(srcPath);
  console.log(`📁 Archivos a analizar: ${files.length}`);
  
  // Analizar archivos
  let allIssues = [];
  
  for (const file of files) {
    const issues = analyzeFile(file);
    allIssues = allIssues.concat(issues);
  }
  
  // Verificar angular.json
  const configIssues = checkAngularJson(projectPath);
  allIssues = allIssues.concat(configIssues);
  
  // Generar y mostrar reporte
  const report = generateReport(allIssues);
  printReport(report);
}

main();
