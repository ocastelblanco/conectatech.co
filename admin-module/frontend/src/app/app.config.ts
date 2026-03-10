import { ApplicationConfig, provideBrowserGlobalErrorListeners, provideZoneChangeDetection } from '@angular/core';
import { provideRouter } from '@angular/router';
import { provideAnimationsAsync } from '@angular/platform-browser/animations/async';
import { provideHttpClient, withFetch, withInterceptors } from '@angular/common/http';
import { providePrimeNG } from 'primeng/config';
import Aura from '@primeuix/themes/aura';
import { definePreset } from '@primeuix/themes';

import { routes } from './app.routes';
import { credentialsInterceptor } from './core/interceptors/credentials.interceptor';

const ConectaTechPreset = definePreset(Aura, {
  semantic: {
    primary: {
      50:  '{sky.50}',
      100: '{sky.100}',
      200: '{sky.200}',
      300: '{sky.300}',
      400: '{sky.400}',
      500: '#4A90E2',
      600: '#4283d5',
      700: '#3973c4',
      800: '#3063b3',
      900: '#234994',
      950: '#173170',
    },
    colorScheme: {
      light: {
        primary: {
          color:        '#4A90E2',
          inverseColor: '#ffffff',
          hoverColor:   '#3A7BC8',
          activeColor:  '#2C6AAF',
        },
        highlight: {
          background:     'rgba(74,144,226,0.12)',
          focusBackground:'rgba(74,144,226,0.2)',
          color:          '#1D2B36',
          focusColor:     '#1D2B36',
        }
      }
    }
  },
  components: {
    button: {
      root: {
        borderRadius: '8px',
      }
    },
    card: {
      root: {
        borderRadius: '12px',
      }
    },
    inputtext: {
      root: {
        borderRadius: '8px',
      }
    }
  }
});

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideZoneChangeDetection({ eventCoalescing: true }),
    provideRouter(routes),
    provideAnimationsAsync(),
    provideHttpClient(
      withFetch(),
      withInterceptors([credentialsInterceptor])
    ),
    providePrimeNG({
      theme: {
        preset: ConectaTechPreset,
        options: {
          darkModeSelector: '.dark',
          cssLayer: {
            name: 'primeng',
            order: 'tailwind-base, primeng, tailwind-utilities'
          }
        }
      },
      ripple: true,
    }),
  ],
};
