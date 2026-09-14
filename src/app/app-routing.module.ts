import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AuthGuard } from './guards/auth.guard';
import { SelectivePreloadStrategy } from './core/strategies/selective-preload.strategy';

const routes: Routes = [
  {
    path: '',
    redirectTo: 'welcome',
    pathMatch: 'full',
  },

  {
    path: 'welcome',
    loadChildren: () =>
      import('./features/welcome/welcome.module').then(
        m => m.WelcomePageModule
      ),
  },
  {
    path: 'login',
    data: { preload: true },
    loadChildren: () =>
      import('./features/auth/login/login.module').then(
        m => m.LoginPageModule
      ),
  },
  {
    path: 'register',
    data: { preload: true },
    loadChildren: () =>
      import('./features/auth/register/register.module').then(
        m => m.RegisterPageModule
      ),
  },
  {
    path: 'recover-password',
    loadChildren: () =>
      import('./features/auth/recover-password/recover-password.module').then(
        m => m.RecoverPasswordPageModule
      ),
  },
  {
    path: 'reset-password',
    loadChildren: () =>
      import('./features/auth/reset-password/reset-password.module').then(
        m => m.ResetPasswordPageModule
      ),
  },

  {
    path: 'home',
    canActivate: [AuthGuard],
    data: { preload: true },
    loadChildren: () =>
      import('./features/home/home.module').then(
        m => m.HomePageModule
      ),
  },
  {
    path: 'procedures',
    canActivate: [AuthGuard],
    data: { preload: true },
    loadChildren: () =>
      import('./features/procedures/procedures.module').then(
        m => m.ProceduresPageModule
      ),
  },
  {
    path: 'profile',
    canActivate: [AuthGuard],
    data: { preload: true },
    loadChildren: () =>
      import('./features/profile/profile.module').then(
        m => m.ProfilePageModule
      ),
  },
  {
    path: 'settings',
    canActivate: [AuthGuard],
    loadChildren: () =>
      import('./features/settings/settings.module').then(
        m => m.SettingsPageModule
      ),
  },
  {
    path: 'help-contact',
    canActivate: [AuthGuard],
    loadChildren: () =>
      import('./features/help-contact/help-contact.module').then(
        m => m.HelpContactPageModule
      ),
  },
  {
    path: 'legal',
    canActivate: [AuthGuard],
    loadChildren: () =>
      import('./features/legal/legal.module').then(
        m => m.LegalPageModule
      ),
  },
  {
    path: 'notifications',
    canActivate: [AuthGuard],
    loadChildren: () =>
      import('./features/notifications/notifications.module').then(
        m => m.NotificationsPageModule
      ),
  },
  {
    path: 'data-protection',
    canActivate: [AuthGuard],
    loadChildren: () =>
      import('./features/data-protection/data-protection.module').then(
        m => m.DataProtectionPageModule
      ),
  },
  {
    path: 'procedures/:slug',
    canActivate: [AuthGuard],
    loadChildren: () =>
      import('./features/procedures/procedure-detail/procedure-detail.module').then(
        m => m.ProcedureDetailPageModule
      ),
  },
  {
    path: 'request-create',
    canActivate: [AuthGuard],
    loadChildren: () =>
      import('./features/requests/request-create/request-create.module').then(
        m => m.RequestCreatePageModule
      ),
  },
  {
    path: 'request-tracking',
    canActivate: [AuthGuard],
    loadChildren: () =>
      import('./features/requests/request-tracking/request-tracking.module').then(
        m => m.RequestTrackingPageModule
      ),
  },
  {
    path: 'request-tracking/:trackingNumber',
    canActivate: [AuthGuard],
    loadChildren: () =>
      import('./features/requests/request-detail/request-detail.module').then(
        m => m.RequestDetailPageModule
      ),
  },

  {
    path: 'appointments',
    canActivate: [AuthGuard],
    loadChildren: () =>
      import('./features/appointments/appointments.module').then(
        m => m.AppointmentsPageModule
      ),
  },

  {
    path: 'announcements',
    canActivate: [AuthGuard],
    loadChildren: () =>
      import('./features/announcements/list/announcement-list.module').then(
        m => m.AnnouncementListPageModule
      ),
  },
  {
    path: 'announcements/:id',
    canActivate: [AuthGuard],
    loadChildren: () =>
      import('./features/announcements/detail/announcement-detail.module').then(
        m => m.AnnouncementDetailPageModule
      ),
  },
  {
    path: 'record-search',
    loadChildren: () =>
      import('./features/record-search/record-search.module').then(
        m => m.RecordSearchPageModule
      ),
  },
  {
    path: 'my-procedures',
    canActivate: [AuthGuard],
    loadChildren: () =>
      import('./features/my-procedures/my-procedures.module').then(
        m => m.MyProceduresPageModule
      ),
  },
  {
    path: 'procedure-detail/:id',
    canActivate: [AuthGuard],
    loadChildren: () =>
      import('./features/procedure-detail/procedure-detail.module').then(
        m => m.ProcedureDetailPageModule
      ),
  },
  // ===== RUTAS PÚBLICAS VISITANTE (sin AuthGuard) =====
  {
    path: 'tarjeta-turismo',
    loadChildren: () =>
      import('./features/tourism-card/tarjeta-turismo/tarjeta-turismo.module').then(
        m => m.TarjetaTurismoPageModule
      ),
  },
  {
    path: 'tarjeta-turismo/pago/:code',
    loadChildren: () =>
      import('./features/tourism-card/pago-turismo/pago-turismo.module').then(
        m => m.PagoTurismoPageModule
      ),
  },
  {
    path: 'tarjeta-turismo/recibo/:code',
    loadChildren: () =>
      import('./features/tourism-card/recibo-turismo/recibo-turismo.module').then(
        m => m.ReciboTurismoPageModule
      ),
  },
  {
    path: 'consultar-tarjeta-turismo',
    loadChildren: () =>
      import('./features/tourism-card/consultar-tarjeta-turismo/consultar-tarjeta-turismo.module').then(
        m => m.ConsultarTarjetaTurismoPageModule
      ),
  },
  {
    path: 'validar-tarjeta-turismo/qr/:qr_token',
    loadChildren: () =>
      import('./features/tourism-card/validar-tarjeta-turismo/validar-tarjeta-turismo.module').then(
        m => m.ValidarTarjetaTurismoPageModule
      ),
  },
  {
    path: 'validar-tarjeta-turismo/:code',
    loadChildren: () =>
      import('./features/tourism-card/validar-tarjeta-turismo/validar-tarjeta-turismo.module').then(
        m => m.ValidarTarjetaTurismoPageModule
      ),
  },

  {
    path: 'admin',
    data: { preload: false },
    loadChildren: () =>
      import('./features/admin/admin.module').then(
        m => m.AdminPageModule
      ),
  },

  {
    path: '**',
    redirectTo: 'welcome',
  },
];

@NgModule({
  imports: [
    RouterModule.forRoot(routes, {
      preloadingStrategy: SelectivePreloadStrategy,
    }),
  ],
  exports: [RouterModule],
})
export class AppRoutingModule {}