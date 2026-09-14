import { Injectable } from '@angular/core';
import { CanActivate, Router, ActivatedRouteSnapshot } from '@angular/router';
import { AuthService } from '../services/auth.service';

@Injectable({ providedIn: 'root' })
export class AdminGuard implements CanActivate {
  constructor(
    private readonly authService: AuthService,
    private readonly router: Router,
  ) {}

  canActivate(): boolean {
    if (this.authService.isAuthenticated() && this.authService.hasAnyRole('admin', 'funcionario')) {
      return true;
    }
    if (this.authService.isAuthenticated()) {
      this.router.navigate(['/home']);
      return false;
    }
    this.router.navigate(['/admin/login']);
    return false;
  }
}
