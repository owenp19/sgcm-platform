import { Component, OnInit } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { AuthService, User } from '../../../services/auth.service';
import { addIcons } from 'ionicons';
import {
  personOutline,
  mailOutline,
  callOutline,
  cardOutline,
  idCardOutline,
  lockClosedOutline,
  createOutline,
  checkmarkOutline,
  closeOutline,
  eyeOutline,
  eyeOffOutline,
  checkmarkCircleOutline,
  alertCircleOutline,
} from 'ionicons/icons';

@Component({
  selector: 'app-admin-profile',
  templateUrl: './profile.page.html',
  styleUrls: ['./profile.page.scss'],
  standalone: false,
})
export class AdminProfilePage implements OnInit {
  profile: User = {
    id: 0, firstName: '', lastName: '', email: '',
    phone: '', documentType: '', documentNumber: '', photoUrl: '', roles: [],
  };

  editForm = { firstName: '', lastName: '', phone: '', documentType: '', documentNumber: '' };
  isEditing = false;
  isSaving = false;
  isLoading = true;
  errorMessage = '';
  successMessage = '';

  isChangingPassword = false;
  isSavingPassword = false;
  passwordForm = { currentPassword: '', newPassword: '', confirmPassword: '' };
  showCurrentPassword = false;
  showNewPassword = false;
  showConfirmPassword = false;

  isChangingEmail = false;
  isSavingEmail = false;
  emailForm = { newEmail: '', currentPassword: '' };
  showEmailPassword = false;

  readonly documentTypes = [
    { value: 'CC', label: 'Cédula de ciudadanía' },
    { value: 'TI', label: 'Tarjeta de identidad' },
    { value: 'CE', label: 'Cédula de extranjería' },
    { value: 'PA', label: 'Pasaporte' },
  ];

  constructor(private readonly authService: AuthService) {
    addIcons({
      personOutline, mailOutline, callOutline, cardOutline,
      idCardOutline, lockClosedOutline, createOutline,
      checkmarkOutline, closeOutline, eyeOutline, eyeOffOutline,
      checkmarkCircleOutline, alertCircleOutline,
    });
  }

  async ngOnInit(): Promise<void> {
    await this.loadProfile();
  }

  get userInitials(): string {
    const first = this.profile.firstName?.trim().charAt(0) || '';
    const last = this.profile.lastName?.trim().charAt(0) || '';
    return `${first}${last}`.toUpperCase();
  }

  get roleLabels(): string {
    const labels: Record<string, string> = { funcionario: 'Funcionario', admin: 'Administrador', ciudadano: 'Ciudadano' };
    return (this.profile.roles || []).map(r => labels[r] || r).join(', ');
  }

  get isFormValid(): boolean {
    return this.editForm.firstName.trim().length >= 2 && this.editForm.lastName.trim().length >= 2;
  }

  get isPasswordFormValid(): boolean {
    return (
      this.passwordForm.currentPassword.length > 0 &&
      this.passwordForm.newPassword.length >= 6 &&
      this.passwordForm.newPassword === this.passwordForm.confirmPassword
    );
  }

  get isEmailFormValid(): boolean {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return (
      emailRegex.test(this.emailForm.newEmail) &&
      this.emailForm.currentPassword.length > 0 &&
      this.emailForm.newEmail !== this.profile.email
    );
  }

  private async loadProfile(): Promise<void> {
    this.isLoading = true;
    try {
      const res = await firstValueFrom(this.authService.getProfile());
      if (res?.user) this.profile = res.user;
    } catch {
      const local = this.authService.getUser();
      if (local) this.profile = local;
    } finally {
      this.isLoading = false;
    }
  }

  startEditing(): void {
    this.editForm = {
      firstName: this.profile.firstName,
      lastName: this.profile.lastName,
      phone: this.profile.phone || '',
      documentType: this.profile.documentType || '',
      documentNumber: this.profile.documentNumber || '',
    };
    this.isEditing = true;
    this.errorMessage = '';
    this.successMessage = '';
  }

  cancelEditing(): void {
    this.isEditing = false;
    this.errorMessage = '';
  }

  async saveProfile(): Promise<void> {
    if (this.isSaving || !this.isFormValid) return;
    this.isSaving = true;
    this.errorMessage = '';
    try {
      const res = await firstValueFrom(this.authService.updateProfile(this.editForm));
      if (res?.user) {
        this.profile = res.user;
        const token = this.authService.getToken();
        if (token) await this.authService.saveSession(token, res.user);
      }
      this.isEditing = false;
      this.successMessage = 'Perfil actualizado';
      setTimeout(() => this.successMessage = '', 3000);
    } catch (error: any) {
      this.errorMessage = error.error?.error || 'Error al guardar';
    } finally {
      this.isSaving = false;
    }
  }

  startChangingPassword(): void {
    this.passwordForm = { currentPassword: '', newPassword: '', confirmPassword: '' };
    this.showCurrentPassword = false;
    this.showNewPassword = false;
    this.showConfirmPassword = false;
    this.isChangingPassword = true;
    this.errorMessage = '';
    this.successMessage = '';
  }

  cancelChangingPassword(): void {
    this.isChangingPassword = false;
    this.errorMessage = '';
  }

  async savePassword(): Promise<void> {
    if (this.isSavingPassword || !this.isPasswordFormValid) return;
    this.isSavingPassword = true;
    this.errorMessage = '';
    try {
      await firstValueFrom(this.authService.changePassword(this.passwordForm.currentPassword, this.passwordForm.newPassword));
      this.isChangingPassword = false;
      this.successMessage = 'Contraseña actualizada';
      setTimeout(() => this.successMessage = '', 3000);
    } catch (error: any) {
      this.errorMessage = error.error?.error || 'Error al cambiar contraseña';
    } finally {
      this.isSavingPassword = false;
    }
  }

  startChangingEmail(): void {
    this.emailForm = { newEmail: '', currentPassword: '' };
    this.showEmailPassword = false;
    this.isChangingEmail = true;
    this.errorMessage = '';
    this.successMessage = '';
  }

  cancelChangingEmail(): void {
    this.isChangingEmail = false;
    this.errorMessage = '';
  }

  async saveEmail(): Promise<void> {
    if (this.isSavingEmail || !this.isEmailFormValid) return;
    this.isSavingEmail = true;
    this.errorMessage = '';
    try {
      const res = await firstValueFrom(this.authService.changeEmail(this.emailForm.newEmail, this.emailForm.currentPassword));
      if (res?.email) {
        this.profile.email = res.email;
        const token = this.authService.getToken();
        if (token) await this.authService.saveSession(token, this.profile);
      }
      this.isChangingEmail = false;
      this.successMessage = 'Correo actualizado';
      setTimeout(() => this.successMessage = '', 3000);
    } catch (error: any) {
      this.errorMessage = error.error?.error || 'Error al cambiar correo';
    } finally {
      this.isSavingEmail = false;
    }
  }
}
