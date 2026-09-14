import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonicModule } from '@ionic/angular';
import { FormsModule } from '@angular/forms';

import { RequestCreatePageRoutingModule } from './request-create-routing.module';
import { RequestCreatePage } from './request-create.page';
import { SharedModule } from '../../../shared/shared-module';

@NgModule({
  imports: [
    CommonModule,
    IonicModule,
    FormsModule,
    RequestCreatePageRoutingModule,
    SharedModule,
  ],
  declarations: [RequestCreatePage],
})
export class RequestCreatePageModule {}
