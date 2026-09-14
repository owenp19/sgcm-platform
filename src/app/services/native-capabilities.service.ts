import { Injectable } from '@angular/core';
import { Capacitor } from '@capacitor/core';
import { Preferences } from '@capacitor/preferences';
import { Geolocation } from '@capacitor/geolocation';
import { Share } from '@capacitor/share';
import { Network } from '@capacitor/network';
import { ToastController } from '@ionic/angular';

export interface OfficePosition {
  latitude: number;
  longitude: number;
  accuracyMeters: number | null;
  capturedAt: string;
}

export interface QrScanResult {
  success: boolean;
  code?: string;
  format?: string;
  error?: string;
}

/**
 * Servicio de capacidades nativas (Capacitor 8 / Capacitor MLKit).
 * ✅ @capacitor-mlkit/barcode-scanning  → escáner QR/código (ventanilla)
 * ✅ @capacitor/geolocation            → coordenadas de visitas y censos
 * ✅ @capacitor/preferences            → caché offline / sesión
 * ✅ @capacitor/network                → detección de conexión
 * ✅ @capacitor/share                  → compartir constancias/PDF
 *
 * TODAS las funciones degradan con un toast informativo cuando se ejecutan
 * en navegador (no aplican plugins nativos fuera de la app empaquetada).
 */
@Injectable({ providedIn: 'root' })
export class NativeCapabilitiesService {
  private readonly isNative = Capacitor.isNativePlatform();
  private barcodeModule: any = null;

  constructor(private readonly toastCtrl: ToastController) {}

  private async toast(msg: string, color: 'success' | 'warning' | 'danger' | 'medium' = 'medium') {
    const t = await this.toastCtrl.create({ message: msg, duration: 3200, position: 'bottom', color });
    await t.present();
  }

  /* ═══════════════════════════ 1. ESCÁNER QR / CÓDIGO ═══════════════════════════ */

  /**
   * Escanea un código QR/código de barras con el plugin MLKit de Capacitor.
   * Uso: verificación de identidad de ciudadano en ventanilla o lectura de la
   * referencia de un expediente.
   */
  async scanQrCode(): Promise<QrScanResult> {
    if (!this.isNative) {
      void this.toast('El escáner QR está disponible solo en la app nativa (ventanilla).', 'warning');
      return { success: false, error: 'NO_NATIVE' };
    }
    try {
      const mod = await this.barcodeModuleLoader();
      const support = await mod.BarcodeScanner.isSupported();
      if (!support.supported) {
        void this.toast('Escáner no soportado en este equipo.', 'danger');
        return { success: false, error: 'UNSUPPORTED' };
      }
      const perms = await mod.BarcodeScanner.requestPermissions();
      if (perms.camera !== 'granted') {
        void this.toast('Sin permiso de cámara para escanear.', 'danger');
        return { success: false, error: 'PERMISSION_DENIED' };
      }
      await mod.BarcodeScanner.prepare({ isManualClose: true });
      try {
        const { content, format } = await mod.BarcodeScanner.startScan({
          formats: ['QR_CODE', 'PDF_417', 'CODE_128', 'DATA_MATRIX', 'EAN_13', 'EAN_8', 'UPCA', 'UPC_E', 'CODE_39', 'CODE_93', 'AZTEC', 'ITF'],
          lensFacing: 'back',
          scanResultDurationInMs: 300,
        });
        if (content) return { success: true, code: content, format };
        return { success: false, error: 'EMPTY_RESULT' };
      } finally {
        void mod.BarcodeScanner.stopScan().catch(() => { /* escáner ya cerrado */ });
      }
    } catch (e: any) {
      console.error('[Native] Error al escanear:', e);
      return { success: false, error: e?.message || 'SCAN_FAILED' };
    }
  }

  /** Carga perezosa del plugin MLKit (no infla el bundle web inicial). */
  private async barcodeModuleLoader(): Promise<any> {
    if (this.barcodeModule) return this.barcodeModule;
    try {
      this.barcodeModule = await import('@capacitor-mlkit/barcode-scanning');
      return this.barcodeModule;
    } catch {
      this.barcodeModule = null;
      throw new Error('PLUGIN_MLKIT_NO_DISPONIBLE');
    }
  }

  /* ═══════════════════════════ 2. GPS / GEOLOCALIZACIÓN ═══════════════════════════ */

  /**
   * Toma las coordenadas actuales (visitas, censos, levantamientos).
   * Devuelve null si el GPS está apagado o se denegó el permiso.
   */
  async getCurrentPosition(): Promise<OfficePosition | null> {
    if (!this.isNative) {
      void this.toast('La geolocalización nativa solo opera en el dispositivo.', 'warning');
      return null;
    }
    try {
      const perm = await Geolocation.checkPermissions();
      if (perm.location === 'denied') {
        const req = await Geolocation.requestPermissions();
        if (req.location === 'denied') {
          void this.toast('Sin permiso de ubicación. Actívalo en Ajustes.', 'danger');
          return null;
        }
      }
      const pos = await Geolocation.getCurrentPosition({
        enableHighAccuracy: true,
        timeout: 15000,
        maximumAge: 0,
      });
      return {
        latitude: pos.coords.latitude,
        longitude: pos.coords.longitude,
        accuracyMeters: pos.coords.accuracy ?? null,
        capturedAt: new Date().toISOString(),
      };
    } catch (e: any) {
      console.error('[Native] Error de ubicación:', e);
      void this.toast('No se pudo obtener la ubicación (¿GPS apagado?).', 'warning');
      return null;
    }
  }

  /* ═══════════════════════ 3. CACHÉ / PREFERENCIAS (OFFLINE) ═══════════════════════ */

  /** Guarda un objeto en caché local (prefijo `occ.`). Útil para zonas sin cobertura. */
  async cacheSet(key: string, value: unknown): Promise<void> {
    try {
      await Preferences.set({ key: `occ.${key}`, value: JSON.stringify(value ?? null) });
    } catch (e) {
      console.warn('[Native] cacheSet falló:', e);
    }
  }

  async cacheGet<T = unknown>(key: string): Promise<T | null> {
    try {
      const { value } = await Preferences.get({ key: `occ.${key}` });
      if (value == null) return null;
      return JSON.parse(value) as T;
    } catch {
      return null;
    }
  }

  async cacheRemove(key: string): Promise<void> {
    try {
      await Preferences.remove({ key: `occ.${key}` });
    } catch { /* noop */ }
  }

  /* ═══════════════════════════ 4. RED / CONECTIVIDAD ═══════════════════════════ */

  /** True si hay conexión a internet (útil para modo offline de censos). */
  async isOnline(): Promise<boolean> {
    try {
      const status = await Network.getStatus();
      return status.connected;
    } catch {
      return navigator.onLine;
    }
  }

  /* ═══════════════════════════ 5. COMPARTIR DOCUMENTOS ═══════════════════════════ */

  /** Comparte un PDF/constancia vía la hoja nativa del dispositivo. */
  async shareDocument(blobUrl: string, title: string, text?: string): Promise<boolean> {
    if (!this.isNative) {
      void this.toast('Compartir está disponible en la app nativa.', 'warning');
      return false;
    }
    try {
      await Share.share({ url: blobUrl, title, text });
      return true;
    } catch (e) {
      console.warn('[Native] share cancelado:', e);
      return false;
    }
  }
}
