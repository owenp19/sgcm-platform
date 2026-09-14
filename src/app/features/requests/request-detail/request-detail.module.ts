import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonicModule } from '@ionic/angular';
import { FormsModule } from '@angular/forms';

import { RequestDetailPageRoutingModule } from './request-detail-routing.module';
import { RequestDetailPage } from './request-detail.page';
import { SharedModule } from '../../../shared/shared-module';

@NgModule({
  imports: [
    CommonModule,
    IonicModule,
    FormsModule,
    RequestDetailPageRoutingModule,
    SharedModule,
  ],
  declarations: [RequestDetailPage],
})
export class RequestDetailPageModule {}
