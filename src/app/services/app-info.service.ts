import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { App } from '@capacitor/app';
import { Capacitor } from '@capacitor/core';
import { firstValueFrom } from 'rxjs';
import { ApiConfigService } from './api-config.service';

export interface ServerAppVersion {
  version: string;
  name?: string;
  timestamp?: string;
}

@Injectable({ providedIn: 'root' })
export class AppInfoService {
  private cachedVersion: string | null = null;

  constructor(
    private readonly http: HttpClient,
    private readonly apiConfig: ApiConfigService,
  ) {}

  async getAppVersion(): Promise<string> {
    if (this.cachedVersion) return this.cachedVersion;
    try {
      const info = await App.getInfo();
      this.cachedVersion = info.version;
    } catch {
      this.cachedVersion = '1.0.0';
    }
    return this.cachedVersion;
  }

  isNative(): boolean {
    return Capacitor.isNativePlatform();
  }

  async getServerVersion(): Promise<ServerAppVersion | null> {
    try {
      return await firstValueFrom(
        this.http.get<ServerAppVersion>(`${this.apiConfig.getApiUrl()}/app/version`),
      );
    } catch {
      return null;
    }
  }

  compareVersions(a: string, b: string): number {
    const pa = a.split('.').map(n => parseInt(n, 10) || 0);
    const pb = b.split('.').map(n => parseInt(n, 10) || 0);
    const len = Math.max(pa.length, pb.length);
    for (let i = 0; i < len; i++) {
      const na = pa[i] ?? 0;
      const nb = pb[i] ?? 0;
      if (na !== nb) return na - nb;
    }
    return 0;
  }
}
