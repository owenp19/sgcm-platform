import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonicModule } from '@ionic/angular';

import { DataProtectionPageRoutingModule } from './data-protection-routing.module';
import { DataProtectionPage } from './data-protection.page';
import { SharedModule } from '../../shared/shared-module';

@NgModule({
  imports: [
    CommonModule,
    IonicModule,
    DataProtectionPageRoutingModule,
    SharedModule,
  ],
  declarations: [DataProtectionPage],
})
export class DataProtectionPageModule {}
