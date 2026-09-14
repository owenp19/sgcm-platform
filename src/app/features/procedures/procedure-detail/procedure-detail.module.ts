import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonicModule } from '@ionic/angular';
import { FormsModule } from '@angular/forms';

import { ProcedureDetailPageRoutingModule } from './procedure-detail-routing.module';
import { ProcedureDetailPage } from './procedure-detail.page';
import { SharedModule } from '../../../shared/shared-module';

@NgModule({
  imports: [
    CommonModule,
    FormsModule,
    IonicModule,
    ProcedureDetailPageRoutingModule,
    SharedModule,
  ],
  declarations: [ProcedureDetailPage],
})
export class ProcedureDetailPageModule {}
