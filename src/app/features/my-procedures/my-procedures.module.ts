import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { IonicModule } from '@ionic/angular';
import { FormsModule } from '@angular/forms';
import { MyProceduresPageRoutingModule } from './my-procedures-routing.module';
import { MyProceduresPage } from './my-procedures.page';
import { SharedModule } from '../../shared/shared-module';

@NgModule({
  imports: [
    CommonModule,
    IonicModule,
    FormsModule,
    MyProceduresPageRoutingModule,
    SharedModule,
  ],
  declarations: [MyProceduresPage],
})
export class MyProceduresPageModule {}
