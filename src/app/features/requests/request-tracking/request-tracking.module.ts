import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonicModule } from '@ionic/angular';

import { RequestTrackingPageRoutingModule } from './request-tracking-routing.module';
import { RequestTrackingPage } from './request-tracking.page';
import { SharedModule } from '../../../shared/shared-module';

@NgModule({
  imports: [
    CommonModule,
    IonicModule,
    RequestTrackingPageRoutingModule,
    SharedModule,
  ],
  declarations: [RequestTrackingPage],
})
export class RequestTrackingPageModule {}
