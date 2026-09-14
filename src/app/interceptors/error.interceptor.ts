import { Injectable } from '@angular/core';
import { HttpInterceptor, HttpRequest, HttpHandler, HttpEvent, HttpErrorResponse } from '@angular/common/http';
import { Observable, throwError, TimeoutError } from 'rxjs';
import { catchError } from 'rxjs/operators';
import { ToastController } from '@ionic/angular';
import { TranslationService } from '../services/translation.service';

@Injectable()
export class ErrorInterceptor implements HttpInterceptor {
  private activeToast: HTMLIonToastElement | null = null;
  private lastToastTime = 0;
  private readonly toastCooldown = 2000;

  constructor(
    private readonly toast: ToastController,
    private readonly translation: TranslationService,
  ) {}

  intercept(req: HttpRequest<unknown>, next: HttpHandler): Observable<HttpEvent<unknown>> {
    if (req.headers.has('X-Skip-Error-Toast')) {
      return next.handle(req.clone({ headers: req.headers.delete('X-Skip-Error-Toast') })).pipe(
        catchError(error => throwError(() => error)),
      );
    }

    if (req.url.includes('/health') || req.url.includes('/device') || req.url.includes('/push')) {
      return next.handle(req).pipe(
        catchError(error => throwError(() => error)),
      );
    }

    return next.handle(req).pipe(
      catchError((error: HttpErrorResponse | TimeoutError) => {
        if (error instanceof TimeoutError) {
          return this.handleError(req, 'errors.timeout', error);
        }

        if (error.status === 401 || error.status === 403) {
          return throwError(() => error);
        }

        let messageKey = 'errors.generic';

        if (error.status === 0) {
          messageKey = 'errors.offline';
        } else if (typeof error.error === 'string' && /^\s*</.test(error.error)) {
          messageKey = 'errors.html';
        } else if (error.status >= 500) {
          messageKey = 'errors.server';
        }

        return this.handleError(req, messageKey, error);
      }),
    );
  }

  private handleError(
    req: HttpRequest<unknown>,
    messageKey: string,
    error: unknown,
  ): Observable<never> {
    const now = Date.now();
    if (now - this.lastToastTime < this.toastCooldown) {
      return throwError(() => error);
    }

    if (this.activeToast) {
      this.activeToast.dismiss().catch(() => {});
      this.activeToast = null;
    }

    const position = 'middle';

    this.toast
      .create({
        message: this.translation.translate(messageKey),
        duration: 3500,
        position,
        cssClass: 'occre-toast occre-toast--error',
      })
      .then(t => {
        this.activeToast = t;
        this.lastToastTime = Date.now();
        void t.present();
        t.onWillDismiss().then(() => {
          if (this.activeToast === t) {
            this.activeToast = null;
          }
        });
      });

    return throwError(() => error);
  }
}
