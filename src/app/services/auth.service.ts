import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { SecureStorageService } from './secure-storage.service';
import { ApiConfigService } from './api-config.service';
import { firstValueFrom } from 'rxjs';

export interface User {
  id: number;
  firstName: string;
  lastName: string;
  email: string;
  phone?: string;
  documentType?: string;
  documentNumber?: string;
  photoUrl?: string;
  roles?: string[];
}

interface AuthResponse {
  message: string;
  token: string;
  user: User;
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private get apiUrl(): string {
    return `${this.apiConfig.getApiUrl()}/auth`;
  }
  private readonly TOKEN_KEY = 'occre_token';
  private readonly USER_KEY = 'occre_user';

  constructor(
    private readonly http: HttpClient,
    private readonly router: Router,
    private readonly secureStorage: SecureStorageService,
    private readonly apiConfig: ApiConfigService,
  ) { }

  getToken(): string | null {
    return this.secureStorage.getToken();
  }

  getUser(): User | null {
    const json = this.secureStorage.getUserJson();
    return json ? JSON.parse(json) : null;
  }

  getInitials(): string {
    const user = this.getUser();
    if (!user) return 'US';
    const first = user.firstName.trim().charAt(0) || '';
    const last = user.lastName.trim().charAt(0) || '';
    return `${first}${last}`.toUpperCase();
  }

  isAuthenticated(): boolean {
    return !!this.secureStorage.getToken();
  }

  hasRole(role: string): boolean {
    return this.getUser()?.roles?.includes(role) ?? false;
  }

  hasAnyRole(...roles: string[]): boolean {
    return roles.some(role => this.hasRole(role));
  }

  login(email: string, password: string) {
    return this.http.post<AuthResponse>(`${this.apiUrl}/login`, { email, password }, {
      headers: { 'X-Skip-Error-Toast': 'true' },
    });
  }

  register(data: {
    firstName: string;
    lastName: string;
    email: string;
    password: string;
    documentType?: string;
    documentNumber?: string;
    phone?: string;
  }) {
    return this.http.post<AuthResponse>(`${this.apiUrl}/register`, data);
  }

  async saveSession(token: string, user: User): Promise<void> {
    await this.secureStorage.save(this.TOKEN_KEY, token);
    await this.secureStorage.save(this.USER_KEY, JSON.stringify(user));
  }

  getProfile() {
    return this.http.get<{ user: User }>(`${this.apiUrl}/profile`);
  }

  updateProfile(data: {
    firstName: string;
    lastName: string;
    documentType?: string;
    documentNumber?: string;
    phone?: string;
  }) {
    return this.http.put<{ message: string; user: User }>(`${this.apiUrl}/profile`, data);
  }

  updatePhoto(photoUrl: string) {
    return this.http.put<{ message: string; photoUrl: string }>(`${this.apiUrl}/photo`, { photoUrl });
  }

  forgotPassword(email: string) {
    return this.http.post<{ message: string }>(`${this.apiUrl}/forgot-password`, { email }, {
      headers: { 'X-Skip-Error-Toast': 'true' },
    });
  }

  resetPassword(token: string, password: string) {
    return this.http.post<{ message: string }>(`${this.apiUrl}/reset-password`, { token, password }, {
      headers: { 'X-Skip-Error-Toast': 'true' },
    });
  }

  changePassword(currentPassword: string, newPassword: string) {
    return this.http.put<{ message: string }>(`${this.apiUrl}/change-password`, { currentPassword, newPassword });
  }

  changeEmail(newEmail: string, currentPassword: string) {
    return this.http.put<{ message: string; email: string }>(`${this.apiUrl}/change-email`, { newEmail, currentPassword });
  }

  async logout(): Promise<void> {
    await this.secureStorage.remove(this.TOKEN_KEY);
    await this.secureStorage.remove(this.USER_KEY);
    await this.router.navigate(['/login']);
  }

  getBiometricPreference() {
    return this.http.get<{ biometricEnabled: boolean }>(`${this.apiUrl}/biometric-preference`);
  }

  setBiometricPreference(enabled: boolean) {
    return this.http.put<{ message: string; biometricEnabled: boolean }>(`${this.apiUrl}/biometric-preference`, { enabled });
  }
}
