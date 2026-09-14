import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonicModule } from '@ionic/angular';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

import { AdminPageRoutingModule } from './admin-routing.module';
import { SharedModule } from '../../shared/shared-module';
import { AdminLoginComponent } from './login/admin-login.component';
import { DashboardPage } from './dashboard/dashboard.page';
import { UsersPage } from './users/users.page';
import { DocumentsPage } from './documents/documents.page';
import { AppointmentsPage } from './appointments/appointments.page';
import { RequestsPage } from './requests/requests.page';
import { AuditPage } from './audit/audit.page';
import { AdminLayoutComponent } from './layout/admin-layout.component';
import { ReportsPage } from './reports/reports.page';
import { MyRequestsPage } from './my-requests/my-requests.page';
import { AdminProfilePage } from './profile/profile.page';
import { PopulationMapPage } from './population-map/population-map.page';

@NgModule({
  imports: [
    CommonModule,
    IonicModule,
    FormsModule,
    RouterModule,
    AdminPageRoutingModule,
    SharedModule,
  ],
  declarations: [
    AdminLoginComponent,
    DashboardPage,
    UsersPage,
    DocumentsPage,
    AppointmentsPage,
    RequestsPage,
    AuditPage,
    AdminLayoutComponent,
    ReportsPage,
    MyRequestsPage,
    AdminProfilePage,
    PopulationMapPage,
  ],
})
export class AdminPageModule {}
