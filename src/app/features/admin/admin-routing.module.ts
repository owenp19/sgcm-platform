import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AdminGuard } from '../../guards/admin.guard';
import { AdminLoginComponent } from './login/admin-login.component';
import { DashboardPage } from './dashboard/dashboard.page';
import { UsersPage } from './users/users.page';
import { DocumentsPage } from './documents/documents.page';
import { AppointmentsPage } from './appointments/appointments.page';
import { RequestsPage } from './requests/requests.page';
import { AuditPage } from './audit/audit.page';
import { ReportsPage } from './reports/reports.page';
import { MyRequestsPage } from './my-requests/my-requests.page';
import { AdminProfilePage } from './profile/profile.page';
import { PopulationMapPage } from './population-map/population-map.page';

const routes: Routes = [
  { path: '', redirectTo: 'login', pathMatch: 'full' },
  { path: 'login', component: AdminLoginComponent },
  { path: 'dashboard', component: DashboardPage, canActivate: [AdminGuard] },
  { path: 'reports', component: ReportsPage, canActivate: [AdminGuard] },
  { path: 'users', component: UsersPage, canActivate: [AdminGuard] },
  { path: 'documents', component: DocumentsPage, canActivate: [AdminGuard] },
  { path: 'appointments', component: AppointmentsPage, canActivate: [AdminGuard] },
  { path: 'requests', component: RequestsPage, canActivate: [AdminGuard] },
  { path: 'my-requests', component: MyRequestsPage, canActivate: [AdminGuard] },
  { path: 'audit', component: AuditPage, canActivate: [AdminGuard] },
  { path: 'profile', component: AdminProfilePage, canActivate: [AdminGuard] },
  { path: 'population-map', component: PopulationMapPage, canActivate: [AdminGuard] },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class AdminPageRoutingModule {}
