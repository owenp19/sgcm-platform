import { Injectable } from '@angular/core';
import { Network } from '@capacitor/network';
import { BehaviorSubject, Observable } from 'rxjs';
import { OfflineService } from './offline.service';

@Injectable({ providedIn: 'root' })
export class NetworkService {
  private isConnectedSubject = new BehaviorSubject<boolean>(false);
  public isConnected$: Observable<boolean> = this.isConnectedSubject.asObservable();
  private connectionType = 'unknown';
  private initialized = false;

  constructor(private readonly offline: OfflineService) {
    this.initializeNetworkListener();
  }

  private async initializeNetworkListener(): Promise<void> {
    try {
      const status = await Network.getStatus();
      this.isConnectedSubject.next(status.connected);
      this.connectionType = status.connectionType;
      this.offline.setOnline(status.connected);
      this.initialized = true;

      await Network.addListener('networkStatusChange', (status) => {
        this.isConnectedSubject.next(status.connected);
        this.connectionType = status.connectionType;
        this.offline.setOnline(status.connected);
      });
    } catch {
      this.isConnectedSubject.next(true);
      this.offline.setOnline(true);
      this.initialized = true;
    }
  }

  isOnline(): boolean {
    return this.isConnectedSubject.value;
  }

  isInitialized(): boolean {
    return this.initialized;
  }

  getConnectionType(): string {
    return this.connectionType;
  }
}
