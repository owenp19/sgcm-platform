import { Component, OnDestroy } from '@angular/core';
import {
  Router,
  NavigationStart,
  NavigationEnd,
  NavigationCancel,
  NavigationError,
} from '@angular/router';
import { Subscription } from 'rxjs';
import { Capacitor } from '@capacitor/core';
import { LoadingService } from './services/loading.service';
import { AuthService } from './services/auth.service';
import { DeviceService } from './services/device.service';

@Component({
  selector: 'app-root',
  templateUrl: 'app.component.html',
  styleUrls: ['app.component.scss'],
  standalone: false,
})
export class AppComponent implements OnDestroy {
  isLoading = false;
  showChatbot = true;

  private readonly subs: Subscription[] = [];

  constructor(
    private readonly router: Router,
    private readonly loadingService: LoadingService,
    private readonly authService: AuthService,
    private readonly deviceService: DeviceService,
  ) {

    if (Capacitor.isNativePlatform() && this.authService.isAuthenticated()) {
      this.deviceService.registerPushNotifications().catch((err) => {
        console.warn('[Push] Registro en arranque fallido:', err);
      });
    }

    this.subs.push(
      this.loadingService.loading$.subscribe((loading) => {
        this.isLoading = loading;
      })
    );

    this.router.events.subscribe((event) => {
      if (event instanceof NavigationStart) {
        this.loadingService.show();
      }

      if (
        event instanceof NavigationEnd ||
        event instanceof NavigationCancel ||
        event instanceof NavigationError
      ) {
        setTimeout(() => {
          this.loadingService.hide();
        }, 400);
      }

      if (event instanceof NavigationEnd) {
        const isHome =
          event.urlAfterRedirects === '/home' ||
          event.urlAfterRedirects.startsWith('/home/');

        this.showChatbot = isHome && this.authService.isAuthenticated();
      }
    });
  }

  ngOnDestroy(): void {
    this.subs.forEach((s) => s.unsubscribe());
  }
}
