import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable, firstValueFrom, timer } from 'rxjs';
import { switchMap, retryWhen, take, mergeMap, catchError } from 'rxjs/operators';
import { ApiConfigService } from './api-config.service';
import { NetworkService } from './network.service';

export interface ConnectivityStatus {
  isReachable: boolean;
  lastChecked: Date | null;
  error: string | null;
}

@Injectable({ providedIn: 'root' })
export class ConnectivityService {
  private readonly _status = new BehaviorSubject<ConnectivityStatus>({
    isReachable: true,
    lastChecked: null,
    error: null,
  });

  public readonly status$: Observable<ConnectivityStatus> = this._status.asObservable();
  private checkInProgress = false;

  constructor(
    private readonly http: HttpClient,
    private readonly apiConfig: ApiConfigService,
    private readonly network: NetworkService,
  ) {}

  get isReachable(): boolean {
    return this._status.value.isReachable;
  }

  get lastChecked(): Date | null {
    return this._status.value.lastChecked;
  }

  async check(): Promise<boolean> {
    if (this.checkInProgress) return this._status.value.isReachable;
    this.checkInProgress = true;

    try {
      const url = `${this.apiConfig.getApiUrl()}/health`;
      const response = await firstValueFrom(
        this.http.get<{ status: string }>(url, { timeout: 8000 }).pipe(
          catchError(() => {
            return [{ status: 'error' }];
          }),
        ),
      );

      const reachable = response?.status === 'ok';
      this._status.next({
        isReachable: reachable,
        lastChecked: new Date(),
        error: reachable ? null : 'El servidor respondió pero no es válido',
      });
      return reachable;
    } catch {
      this._status.next({
        isReachable: false,
        lastChecked: new Date(),
        error: 'No se pudo conectar con el servidor',
      });
      return false;
    } finally {
      this.checkInProgress = false;
    }
  }

  async checkWithRetry(maxRetries = 3, baseDelay = 1500): Promise<boolean> {
    for (let attempt = 0; attempt < maxRetries; attempt++) {
      const reachable = await this.check();
      if (reachable) return true;

      if (attempt < maxRetries - 1) {
        const delay = baseDelay * Math.pow(2, attempt);
        await new Promise(resolve => setTimeout(resolve, delay));
      }
    }
    return false;
  }

  async waitForConnectivity(timeoutMs = 30000): Promise<boolean> {
    const start = Date.now();

    while (Date.now() - start < timeoutMs) {
      if (this.network.isOnline()) {
        const reachable = await this.check();
        if (reachable) return true;
      }
      await new Promise(resolve => setTimeout(resolve, 2000));
    }
    return false;
  }

  markUnreachable(): void {
    this._status.next({
      ...this._status.value,
      isReachable: false,
      lastChecked: new Date(),
    });
  }

  markReachable(): void {
    this._status.next({
      ...this._status.value,
      isReachable: true,
      lastChecked: new Date(),
      error: null,
    });
  }
}
