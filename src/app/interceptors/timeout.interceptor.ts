import { Injectable } from '@angular/core';
import { HttpInterceptor, HttpRequest, HttpHandler, HttpEvent } from '@angular/common/http';
import { Observable, TimeoutError } from 'rxjs';
import { timeout } from 'rxjs/operators';

const DEFAULT_TIMEOUT = 20000;

@Injectable()
export class TimeoutInterceptor implements HttpInterceptor {
  intercept(req: HttpRequest<unknown>, next: HttpHandler): Observable<HttpEvent<unknown>> {
    const timeoutValue = req.headers.get('X-Request-Timeout');
    const resolvedTimeout = timeoutValue ? parseInt(timeoutValue, 10) : DEFAULT_TIMEOUT;

    const cloned = req.clone({
      headers: req.headers.delete('X-Request-Timeout'),
    });

    return next.handle(cloned).pipe(timeout(resolvedTimeout));
  }
}
